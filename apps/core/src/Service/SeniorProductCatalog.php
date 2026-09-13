<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

final class SeniorProductCatalog
{
    public function __construct(private readonly DatabaseSchemaInspector $database, private readonly LoggerInterface $logger) {}

    /** @return list<string> */
    public function availableProductColumns(array $settings, string $table = 'E075PRO'): array
    {
        if (!$this->database->isConfigured($settings)) {
            return [];
        }

        try {
            $columns = $this->database->columns($settings, $table)['columns'];

            return array_values(array_filter(array_map('strval', $columns)));
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to inspect the Senior product table.', ['exception' => $exception]);

            return [];
        }
    }

    /**
     * @param array<string, string> $mapping
     * @return array{configured: bool, products: list<array<string, mixed>>, error: string|null, sourceTable: string, recordCount: int, page: int, perPage: int, pageCount: int, ncmUpdateAvailable: bool}
     */
    public function listProducts(array $binding, array $settings, int $page = 1, int $perPage = 10): array
    {
        $table = $this->validTable((string) ($binding['table'] ?? '')) ?: 'E075PRO';
        $mapping = is_array($binding['mapping'] ?? null) ? $binding['mapping'] : [];
        if (empty($binding['table']) || !$this->database->isConfigured($settings)) {
            return $this->emptyResult(false, $table);
        }

        try {
            $columns = $this->columnLookup($settings, $table);
            $selects = [
                $this->selectColumn($mapping['company'] ?? 'CodEmp', 'CodEmp', $columns),
                $this->selectColumn($mapping['product_code'] ?? 'CodPro', 'CodPro', $columns),
                $this->selectColumn($mapping['product_name'] ?? 'DesPro', 'DesPro', $columns),
                $this->selectColumn('CplPro', 'CplPro', $columns),
                $this->selectColumn('DesNFv', 'DesNFv', $columns),
                $this->selectColumn($mapping['family'] ?? 'CodFam', 'CodFam', $columns),
                $this->selectColumn($mapping['unit'] ?? 'UniMed', 'UniMed', $columns),
                $this->selectColumn('TipPro', 'TipPro', $columns),
                $this->selectColumn($mapping['origin_code'] ?? 'CodOri', 'CodOri', $columns),
            ];

            $taxAliases = [
                'ncm' => 'Ncm',
                'cst_pis' => 'CstPis',
                'cst_cofins' => 'CstCofins',
                'cst_icms' => 'CstIcms',
            ];
            foreach ($taxAliases as $mappingKey => $alias) {
                $source = $mapping[$mappingKey] ?? '';
                if ('' !== $source && isset($columns[strtolower($source)])) {
                    $selects[] = $this->selectColumn($source, $alias, $columns);
                }
            }

            $selects = array_values(array_unique(array_filter($selects)));
            $connection = $this->database->open($settings);
            $quotedTable = $this->quoteTable($table);
            $recordCount = (int) $connection->query(sprintf('SELECT COUNT(*) FROM %s', $quotedTable))->fetchColumn();
            $perPage = max(1, min(50, $perPage));
            $pageCount = max(1, (int) ceil($recordCount / $perPage));
            $page = max(1, min($page, $pageCount));
            $selects = array_merge($selects, IntegrationFields::selectedColumns($mapping, $columns));
            $offset = ($page - 1) * $perPage;
            $statement = $connection->query(sprintf(
                'SELECT %s FROM %s ORDER BY %s OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                implode(', ', $selects), $quotedTable, '['.str_replace(']', ']]', $columns[strtolower($mapping['product_code'] ?? 'CodPro')] ?? 'CodPro').']',
                $offset,
                $perPage,
            ));
            $products = $statement->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($products as &$product) {
                foreach (['Ncm', 'CstPis', 'CstCofins', 'CstIcms'] as $field) {
                    $product[$field] ??= '';
                }
            }
            unset($product);

            return [
                'configured' => true,
                'products' => $products,
                'error' => null,
                'sourceTable' => $table,
                'recordCount' => $recordCount,
                'page' => $page,
                'perPage' => $perPage,
                'pageCount' => $pageCount,
                'ncmUpdateAvailable' => $this->mappedColumn($mapping, 'ncm', $columns) !== null,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to read products from the Senior ERP.', ['exception' => $exception]);

            return [
                ...$this->emptyResult(true, $table),
                'error' => 'Não foi possível consultar a base Senior. Verifique o vínculo de banco e o mapeamento de campos.',
            ];
        }
    }

    /** @return array{0: string, 1: string} */
    private function splitTable(string $table): array
    {
        $parts = array_values(array_filter(explode('.', trim($table)), static fn (string $part): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) === 1));

        return 2 === count($parts) ? [$parts[0], $parts[1]] : ['', $parts[0] ?? ''];
    }

    /** @return array<string, string> */
    private function columnLookup(array $settings, string $table): array
    {
        $lookup = [];
        foreach ($this->availableProductColumns($settings, $table) as $column) {
            $lookup[strtolower($column)] = $column;
        }

        return $lookup;
    }

    /** @param array<string, string> $columns */
    private function selectColumn(string $source, string $alias, array $columns): string
    {
        $column = $columns[strtolower($source)] ?? null;
        if (null === $column) {
            return '';
        }

        return sprintf('[%s] AS [%s]', $column, $alias);
    }

    /** @param array<string, string> $mapping
     *  @param array<string, string> $columns
     */
    private function mappedColumn(array $mapping, string $field, array $columns): ?string
    {
        $source = $mapping[$field] ?? '';

        return isset($columns[strtolower($source)]) ? $columns[strtolower($source)] : null;
    }

    /** @return array{configured: bool, products: list<array<string, mixed>>, error: string|null, sourceTable: string, recordCount: int, page: int, perPage: int, pageCount: int, ncmUpdateAvailable: bool} */
    private function emptyResult(bool $configured, string $table): array
    {
        return [
            'configured' => $configured,
            'products' => [],
            'error' => null,
            'sourceTable' => $table,
            'recordCount' => 0,
            'page' => 1,
            'perPage' => 10,
            'pageCount' => 1,
            'ncmUpdateAvailable' => false,
        ];
    }

    private function validTable(string $table): ?string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $table) === 1 ? $table : null;
    }

    private function quoteTable(string $table): string
    {
        return implode('.', array_map(static fn (string $part): string => sprintf('[%s]', $part), explode('.', $table)));
    }
}
