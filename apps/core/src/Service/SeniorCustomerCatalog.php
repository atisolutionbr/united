<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

final class SeniorCustomerCatalog
{
    public function __construct(private readonly DatabaseSchemaInspector $database, private readonly LoggerInterface $logger) {}

    /** @param array<string, mixed> $binding
     *  @return array{configured: bool, customers: list<array<string, mixed>>, error: string|null, sourceTable: string, recordCount: int, page: int, perPage: int, pageCount: int, missingAddressCount: int, staleCount: int}
     */
    public function listCustomers(array $binding, array $settings, int $page = 1, int $perPage = 10): array
    {
        $table = $this->validTable((string) ($binding['table'] ?? '')) ?: 'E085CLI';
        if (empty($binding['table']) || !$this->database->isConfigured($settings)) {
            return $this->emptyResult(false, $table);
        }

        try {
            $pdo = $this->database->open($settings);
            $columns = $this->columns($pdo, $table);
            if ([] === $columns) {
                return [...$this->emptyResult(true, $table), 'error' => sprintf('A tabela %s não possui campos disponíveis para consulta.', $table)];
            }

            $mapping = is_array($binding['mapping'] ?? null) ? $binding['mapping'] : [];
            $selects = [
                $this->select($mapping['company'] ?? 'CodEmp', 'CodEmp', $columns),
                $this->select($mapping['customer_code'] ?? 'CodCli', 'CodCli', $columns),
                $this->select($mapping['name'] ?? 'NomCli', 'NomCli', $columns),
                $this->select($mapping['document'] ?? 'CgcCpf', 'CgcCpf', $columns),
                $this->select($mapping['state_registration'] ?? 'InsEst', 'InsEst', $columns),
                $this->select($mapping['email'] ?? 'EmlCli', 'EmlCli', $columns),
                $this->select('EndCli', 'EndCli', $columns),
                $this->select('CidCli', 'CidCli', $columns),
                $this->select('SigUfs', 'SigUfs', $columns),
                $this->selectFirst(['DatAlt', 'DatAtu', 'DatCad'], 'DatAlt', $columns),
            ];
            $selects = array_merge(array_values(array_filter($selects)), IntegrationFields::selectedColumns($mapping, $columns));
            $recordCount = (int) $pdo->query(sprintf('SELECT COUNT(*) FROM %s', $this->quoteTable($table)))->fetchColumn();
            $perPage = max(1, min(50, $perPage));
            $pageCount = max(1, (int) ceil($recordCount / $perPage));
            $page = max(1, min($page, $pageCount));
            $order = $this->column($mapping['customer_code'] ?? 'CodCli', $columns) ?? array_values($columns)[0];
            $statement = $pdo->query(sprintf(
                'SELECT %s FROM %s ORDER BY [%s] OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                implode(', ', $selects), $this->quoteTable($table), $order, ($page - 1) * $perPage, $perPage,
            ));
            $customers = $statement->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($customers as &$customer) {
                foreach (['CodEmp', 'CodCli', 'NomCli', 'CgcCpf', 'InsEst', 'EmlCli', 'EndCli', 'CidCli', 'SigUfs', 'DatAlt'] as $field) {
                    $customer[$field] ??= '';
                }
            }
            unset($customer);

            $addressColumn = $this->column('EndCli', $columns);
            $missingAddressCount = null === $addressColumn ? 0 : (int) $pdo->query(sprintf("SELECT COUNT(*) FROM %s WHERE NULLIF(LTRIM(RTRIM([%s])), '') IS NULL", $this->quoteTable($table), $addressColumn))->fetchColumn();

            return compact('customers', 'recordCount', 'page', 'perPage', 'pageCount', 'missingAddressCount') + [
                'configured' => true,
                'error' => null,
                'sourceTable' => $table,
                'staleCount' => 0,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to read customers from the Senior ERP.', ['exception' => $exception]);

            return [...$this->emptyResult(true, $table), 'error' => DatabaseSchemaInspector::queryFailure($exception)];
        }
    }

    /** @return array<string, string> */
    private function columns(\PDO $pdo, string $table): array
    {
        [$schema, $name] = $this->splitTable($table);
        $statement = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND (:schema = \'\' OR TABLE_SCHEMA = :schema) ORDER BY ORDINAL_POSITION');
        $statement->execute(['table' => $name, 'schema' => $schema]);
        $lookup = [];
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $column) {
            $lookup[strtolower((string) $column)] = (string) $column;
        }

        return $lookup;
    }

    /** @param array<string, string> $columns */
    private function select(string $candidate, string $alias, array $columns): string
    {
        $column = $this->column($candidate, $columns);

        return null === $column ? sprintf("'' AS [%s]", $alias) : sprintf('[%s] AS [%s]', $column, $alias);
    }

    /** @param list<string> $candidates
     *  @param array<string, string> $columns
     */
    private function selectFirst(array $candidates, string $alias, array $columns): string
    {
        foreach ($candidates as $candidate) {
            if (null !== $this->column($candidate, $columns)) {
                return $this->select($candidate, $alias, $columns);
            }
        }

        return sprintf("'' AS [%s]", $alias);
    }

    /** @param array<string, string> $columns */
    private function column(string $candidate, array $columns): ?string
    {
        return $columns[strtolower($candidate)] ?? null;
    }

    private function validTable(string $table): ?string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $table) === 1 ? $table : null;
    }

    private function quoteTable(string $table): string
    {
        return implode('.', array_map(static fn (string $part): string => sprintf('[%s]', $part), explode('.', $table)));
    }

    /** @return array{0: string, 1: string} */
    private function splitTable(string $table): array
    {
        $parts = explode('.', $table, 2);

        return 2 === count($parts) ? [$parts[0], $parts[1]] : ['', $parts[0]];
    }

    /** @return array{configured: bool, customers: list<array<string, mixed>>, error: string|null, sourceTable: string, recordCount: int, page: int, perPage: int, pageCount: int, missingAddressCount: int, staleCount: int} */
    private function emptyResult(bool $configured, string $table): array
    {
        return ['configured' => $configured, 'customers' => [], 'error' => null, 'sourceTable' => $table, 'recordCount' => 0, 'page' => 1, 'perPage' => 10, 'pageCount' => 1, 'missingAddressCount' => 0, 'staleCount' => 0];
    }
}
