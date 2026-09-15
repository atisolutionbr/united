<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

final class SeniorPartyCatalog
{
    /** @var array<string, array{table: string, code: string, name: string, document: string, address: string, city: string, state: string, phone: string, updated: string}> */
    private const PROFILES = [
        'suppliers' => ['table' => 'E095FOR', 'code' => 'CodFor', 'name' => 'NomFor', 'document' => 'CgcCpf', 'address' => 'EndFor', 'city' => 'CidFor', 'state' => 'SigUfs', 'phone' => 'FonFor', 'updated' => 'DatAtu'],
        'carriers' => ['table' => 'E073TRA', 'code' => 'CodTra', 'name' => 'NomTra', 'document' => 'CgcCpf', 'address' => 'EndTra', 'city' => 'CidTra', 'state' => 'SigUfs', 'phone' => 'FonTra', 'updated' => 'DatAlt'],
    ];

    public function __construct(private readonly DatabaseSchemaInspector $database, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param array<string, mixed> $binding
     * @param array<string, mixed> $settings
     * @return array{configured: bool, parties: list<array<string, mixed>>, error: string|null, sourceTable: string, recordCount: int, page: int, perPage: int, pageCount: int, missingAddressCount: int, staleCount: int}
     */
    public function list(string $type, array $binding, array $settings, int $page = 1, int $perPage = 10): array
    {
        $profile = self::PROFILES[$type] ?? null;
        if (null === $profile) {
            throw new \InvalidArgumentException('Tipo de cadastro inválido.');
        }

        $table = $this->validTable((string) ($binding['table'] ?? '')) ?: $profile['table'];
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
                $this->select($mapping[$type === 'suppliers' ? 'supplier_code' : 'carrier_code'] ?? $profile['code'], 'Code', $columns),
                $this->select($mapping['name'] ?? $profile['name'], 'Name', $columns),
                $this->select($mapping['document'] ?? $profile['document'], 'Document', $columns),
                $this->select($mapping['state_registration'] ?? 'InsEst', 'StateRegistration', $columns),
                $this->select($mapping['email'] ?? '', 'Email', $columns),
                $this->select($profile['address'], 'Address', $columns),
                $this->select($profile['city'], 'City', $columns),
                $this->select($profile['state'], 'State', $columns),
                $this->select($mapping['phone'] ?? $profile['phone'], 'Phone', $columns),
                $this->select($profile['updated'], 'UpdatedAt', $columns),
            ];
            $selects = array_merge(array_values(array_filter($selects)), IntegrationFields::selectedColumns($mapping, $columns));
            $perPage = max(1, min(50, $perPage));
            $page = max(1, $page);
            $order = $this->column($mapping[$type === 'suppliers' ? 'supplier_code' : 'carrier_code'] ?? $profile['code'], $columns) ?? array_values($columns)[0];
            $statement = $pdo->query(sprintf(
                'SELECT TOP %d %s FROM %s ORDER BY [%s]',
                $perPage, implode(', ', $selects), $this->quoteTable($table), $order,
            ));
            $parties = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $page = 1;
            $recordCount = count($parties);
            $pageCount = 1;
            foreach ($parties as &$party) {
                foreach (['Code', 'Name', 'Document', 'StateRegistration', 'Email', 'Address', 'City', 'State', 'Phone', 'UpdatedAt'] as $field) {
                    $party[$field] ??= '';
                }
            }
            unset($party);

            $address = $this->column($profile['address'], $columns);
            $missingAddressCount = 0;

            return compact('parties', 'recordCount', 'page', 'perPage', 'pageCount', 'missingAddressCount') + [
                'configured' => true,
                'error' => null,
                'sourceTable' => $table,
                'staleCount' => 0,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to read records from the Senior ERP.', ['type' => $type, 'exception' => $exception]);

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

    /** @return array{configured: bool, parties: list<array<string, mixed>>, error: string|null, sourceTable: string, recordCount: int, page: int, perPage: int, pageCount: int, missingAddressCount: int, staleCount: int} */
    private function emptyResult(bool $configured, string $table): array
    {
        return ['configured' => $configured, 'parties' => [], 'error' => null, 'sourceTable' => $table, 'recordCount' => 0, 'page' => 1, 'perPage' => 10, 'pageCount' => 1, 'missingAddressCount' => 0, 'staleCount' => 0];
    }
}
