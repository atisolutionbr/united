<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

final class SeniorProductCatalog
{
    public function __construct(private readonly DatabaseSchemaInspector $database, private readonly LoggerInterface $logger) {}

    /** @return list<string> */
    public function availableProductColumns(array $settings): array
    {
        if (!$this->database->isConfigured($settings)) {
            return [];
        }

        try {
            $statement = $this->database->open($settings)->query(<<<'SQL'
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'E075PRO'
                ORDER BY ORDINAL_POSITION
                SQL);
            $columns = $statement->fetchAll(\PDO::FETCH_COLUMN);

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
    public function listProducts(array $mapping, array $settings, int $page = 1, int $perPage = 10): array
    {
        if (!$this->database->isConfigured($settings)) {
            return $this->emptyResult(false);
        }

        try {
            $columns = $this->columnLookup($settings);
            $selects = [
                $this->selectColumn('CodEmp', 'CodEmp', $columns),
                $this->selectColumn('CodPro', 'CodPro', $columns),
                $this->selectColumn('DesPro', 'DesPro', $columns),
                $this->selectColumn('CplPro', 'CplPro', $columns),
                $this->selectColumn('DesNFv', 'DesNFv', $columns),
                $this->selectColumn('CodFam', 'CodFam', $columns),
                $this->selectColumn('UniMed', 'UniMed', $columns),
                $this->selectColumn('TipPro', 'TipPro', $columns),
                $this->selectColumn('CodOri', 'CodOri', $columns),
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
            $recordCount = (int) $connection->query('SELECT COUNT(*) FROM [E075PRO]')->fetchColumn();
            $perPage = max(1, min(50, $perPage));
            $pageCount = max(1, (int) ceil($recordCount / $perPage));
            $page = max(1, min($page, $pageCount));
            $offset = ($page - 1) * $perPage;
            $statement = $connection->query(sprintf(
                'SELECT %s FROM [E075PRO] ORDER BY [CodPro] OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                implode(', ', $selects),
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
                'sourceTable' => 'E075PRO',
                'recordCount' => $recordCount,
                'page' => $page,
                'perPage' => $perPage,
                'pageCount' => $pageCount,
                'ncmUpdateAvailable' => $this->mappedColumn($mapping, 'ncm', $columns) !== null,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to read products from the Senior ERP.', ['exception' => $exception]);

            return [
                ...$this->emptyResult(true),
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
    private function columnLookup(array $settings): array
    {
        $lookup = [];
        foreach ($this->availableProductColumns($settings) as $column) {
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
    private function emptyResult(bool $configured): array
    {
        return [
            'configured' => $configured,
            'products' => [],
            'error' => null,
            'sourceTable' => 'E075PRO',
            'recordCount' => 0,
            'page' => 1,
            'perPage' => 10,
            'pageCount' => 1,
            'ncmUpdateAvailable' => false,
        ];
    }
}
