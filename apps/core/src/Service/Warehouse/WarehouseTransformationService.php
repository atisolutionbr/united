<?php

namespace App\Service\Warehouse;

use App\Entity\Warehouse\AnalyticModel;
use App\Entity\Warehouse\AnalyticsHistory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class WarehouseTransformationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function executeTransformation(AnalyticModel $model): array
    {
        $startTime = microtime(true);
        $result = ['success' => false, 'rows' => 0, 'error' => null];

        $model->setLastRefreshStatus(AnalyticModel::STATUS_RUNNING);
        $this->entityManager->flush();

        try {
            $conn = $this->entityManager->getConnection();

            $sourceTable = $this->sanitizeTableName($model->getSourceTable());
            $targetTable = $this->sanitizeTableName($model->getTargetTable());

            $columns = $this->buildColumnList($model->getDimensions(), $model->getMetrics());
            $whereClause = $this->buildWhereClause($model->getFilters());

            $conn->executeStatement("CREATE SCHEMA IF NOT EXISTS warehouse");
            $conn->executeStatement("DROP TABLE IF EXISTS {$targetTable}");

            $sql = "CREATE TABLE {$targetTable} AS SELECT {$columns} FROM {$sourceTable}";
            if ($whereClause) {
                $sql .= " WHERE {$whereClause}";
            }

            $conn->executeStatement($sql);

            $rowCount = (int) $conn->fetchOne("SELECT COUNT(*) FROM {$targetTable}");

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $model->setLastRefreshStatus(AnalyticModel::STATUS_READY);
            $model->setLastRefreshedAt(new \DateTimeImmutable());
            $model->setRowCount($rowCount);
            $this->entityManager->flush();

            $this->recordHistory(
                AnalyticsHistory::EVENT_WAREHOUSE_REFRESH,
                "Modelo: {$model->getName()}",
                "Tabela: {$targetTable} | Linhas: {$rowCount}",
                AnalyticsHistory::STATUS_SUCCESS,
                $durationMs,
                $rowCount,
            );

            $result = ['success' => true, 'rows' => $rowCount, 'error' => null];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logger->error('Warehouse transformation failed', ['model' => $model->getId(), 'error' => $e->getMessage()]);

            $model->setLastRefreshStatus(AnalyticModel::STATUS_FAILED);
            $this->entityManager->flush();

            $this->recordHistory(
                AnalyticsHistory::EVENT_ETL_FAILURE,
                "Modelo: {$model->getName()}",
                $e->getMessage(),
                AnalyticsHistory::STATUS_FAILED,
                $durationMs,
                0,
            );

            $result = ['success' => false, 'rows' => 0, 'error' => $e->getMessage()];
        }

        return $result;
    }

    public function getStagingTables(): array
    {
        try {
            $conn = $this->entityManager->getConnection();
            $rows = $conn->fetchAllAssociative("
                SELECT table_name, pg_size_pretty(pg_total_relation_size('staging.' || table_name)) as size
                FROM information_schema.tables
                WHERE table_schema = 'staging'
                ORDER BY table_name
            ");
            return array_column($rows, 'table_name');
        } catch (\Throwable) {
            return [];
        }
    }

    public function getWarehouseTables(): array
    {
        try {
            $conn = $this->entityManager->getConnection();
            return $conn->fetchAllAssociative("
                SELECT
                    t.table_schema || '.' || t.table_name AS full_name,
                    t.table_name AS name,
                    pg_size_pretty(pg_total_relation_size(quote_ident(t.table_schema) || '.' || quote_ident(t.table_name))) AS size,
                    (SELECT COUNT(*) FROM information_schema.columns c WHERE c.table_schema = t.table_schema AND c.table_name = t.table_name) AS column_count
                FROM information_schema.tables t
                WHERE t.table_schema = 'warehouse'
                ORDER BY t.table_name
            ");
        } catch (\Throwable) {
            return [];
        }
    }

    public function getPublicStagingTables(): array
    {
        try {
            $conn = $this->entityManager->getConnection();
            $rows = $conn->fetchAllAssociative("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name LIKE 'staging_%'
                ORDER BY table_name
            ");
            return array_column($rows, 'table_name');
        } catch (\Throwable) {
            return [];
        }
    }

    public function getWarehouseStats(): array
    {
        try {
            $conn = $this->entityManager->getConnection();
            $tables = $conn->fetchAllAssociative("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'warehouse'
            ");

            $totalRows = 0;
            foreach ($tables as $t) {
                try {
                    $count = (int) $conn->fetchOne("SELECT COUNT(*) FROM warehouse.{$t['table_name']}");
                    $totalRows += $count;
                } catch (\Throwable) {
                    // table may be empty or not accessible
                }
            }

            return [
                'tables' => count($tables),
                'totalRows' => $totalRows,
            ];
        } catch (\Throwable) {
            return ['tables' => 0, 'totalRows' => 0];
        }
    }

    private function buildColumnList(array $dimensions, array $metrics): string
    {
        $cols = array_merge($dimensions, $metrics);
        if (empty($cols)) {
            return '*';
        }

        return implode(', ', array_map(
            fn(string $col) => preg_replace('/[^a-zA-Z0-9_]/', '', $col),
            $cols,
        ));
    }

    private function buildWhereClause(array $filters): string
    {
        if (empty($filters)) {
            return '';
        }
        $parts = [];
        foreach ($filters as $filter) {
            if (isset($filter['column'], $filter['operator'], $filter['value'])) {
                $col = preg_replace('/[^a-zA-Z0-9_]/', '', $filter['column']);
                $op = match ($filter['operator']) {
                    '=', '!=', '>', '<', '>=', '<=' => $filter['operator'],
                    'IS NULL' => 'IS NULL',
                    'IS NOT NULL' => 'IS NOT NULL',
                    default => '=',
                };
                $val = addslashes((string) $filter['value']);
                $parts[] = "{$col} {$op} '{$val}'";
            }
        }
        return implode(' AND ', $parts);
    }

    private function sanitizeTableName(string $table): string
    {
        // Allow schema.table or plain table names — strip dangerous chars
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $table);
    }

    private function recordHistory(
        string $eventType,
        string $subject,
        ?string $detail,
        string $status,
        int $durationMs,
        int $rowsAffected,
    ): void {
        $history = (new AnalyticsHistory())
            ->setEventType($eventType)
            ->setSubject($subject)
            ->setDetail($detail)
            ->setStatus($status)
            ->setDurationMs($durationMs)
            ->setRowsAffected($rowsAffected);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
