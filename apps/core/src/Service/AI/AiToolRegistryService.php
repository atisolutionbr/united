<?php

namespace App\Service\AI;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Registry of built-in AI tools that agents can invoke.
 * Tools gather real data from the platform to enrich AI context.
 */
class AiToolRegistryService
{
    private array $tools = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
        $this->registerBuiltinTools();
    }

    public function execute(string $toolName, array $params = []): mixed
    {
        if (!isset($this->tools[$toolName])) {
            $this->logger->warning('Unknown AI tool', ['tool' => $toolName]);
            return null;
        }
        return ($this->tools[$toolName])($params);
    }

    public function listTools(): array
    {
        return array_keys($this->tools);
    }

    private function registerBuiltinTools(): void
    {
        $conn = $this->connection;

        $this->tools['buscar_indicadores'] = function (array $params) use ($conn): array {
            try {
                return $conn->fetchAllAssociative(
                    "SELECT metric_name, metric_value, metric_unit, reference_date, estado
                     FROM warehouse.fact_agencias_turismo
                     ORDER BY reference_date DESC
                     LIMIT 20"
                );
            } catch (\Throwable) {
                return [];
            }
        };

        $this->tools['listar_datasets'] = function (array $params) use ($conn): array {
            try {
                return $conn->fetchAllAssociative(
                    "SELECT name, status, record_count, last_ingested_at
                     FROM datasets
                     WHERE status = 'active'
                     ORDER BY name
                     LIMIT 30"
                );
            } catch (\Throwable) {
                return [];
            }
        };

        $this->tools['consultar_warehouse'] = function (array $params) use ($conn): array {
            try {
                return $conn->fetchAllAssociative(
                    "SELECT table_schema, table_name, pg_size_pretty(pg_total_relation_size(quote_ident(table_schema)||'.'||quote_ident(table_name))) as size
                     FROM information_schema.tables
                     WHERE table_schema = 'warehouse'
                     ORDER BY table_name"
                );
            } catch (\Throwable) {
                return [];
            }
        };

        $this->tools['obter_qualidade_dataset'] = function (array $params) use ($conn): array {
            try {
                $name = $params['dataset'] ?? null;
                $sql = "SELECT d.name, qr.overall_score, qr.completeness_score, qr.validity_score, qr.uniqueness_score, qr.created_at
                        FROM quality_reports qr
                        JOIN datasets d ON d.id = qr.dataset_id
                        ORDER BY qr.created_at DESC
                        LIMIT 10";
                return $conn->fetchAllAssociative($sql);
            } catch (\Throwable) {
                return [];
            }
        };

        $this->tools['obter_linhagem_dataset'] = function (array $params) use ($conn): array {
            try {
                return $conn->fetchAllAssociative(
                    "SELECT source_table, target_table, transformation_type, last_run_status, last_run_at
                     FROM analytic_models
                     WHERE is_active = true
                     ORDER BY updated_at DESC
                     LIMIT 10"
                );
            } catch (\Throwable) {
                return [];
            }
        };

        $this->tools['gerar_relatorio'] = function (array $params) use ($conn): array {
            try {
                return [
                    'tipo' => 'resumo_executivo',
                    'gerado_em' => (new \DateTimeImmutable())->format('Y-m-d H:i'),
                    'datasets_ativos' => $conn->fetchOne("SELECT COUNT(*) FROM datasets WHERE status = 'active'") ?? 0,
                    'ingestoes_hoje' => $conn->fetchOne("SELECT COUNT(*) FROM ingestion_runs WHERE DATE(created_at) = CURRENT_DATE") ?? 0,
                ];
            } catch (\Throwable) {
                return [];
            }
        };
    }
}
