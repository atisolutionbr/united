<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SeniorProductCatalog
{
    /** @var list<string>|null */
    private ?array $productColumns = null;

    public function __construct(
        #[Autowire('%env(SENIOR_DATABASE_DSN)%')]
        private readonly string $databaseDsn,
        #[Autowire('%env(SENIOR_DATABASE_USER)%')]
        private readonly string $databaseUser,
        #[Autowire('%env(SENIOR_DATABASE_PASSWORD)%')]
        private readonly string $databasePassword,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->databaseDsn) && '' !== trim($this->databaseUser);
    }

    /** @return array<string, scalar|bool|null> */
    public function defaultConnectionSettings(): array
    {
        $host = '';
        $database = '';
        $port = '1433';

        foreach (explode(';', $this->databaseDsn) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $key = strtolower(trim($key));
            $value = trim($value);

            if ('host' === $key) {
                [$host, $dsnPort] = array_pad(explode(':', $value, 2), 2, '');
                $port = '' !== $dsnPort ? $dsnPort : $port;
            }

            if ('dbname' === $key) {
                $database = $value;
            }
        }

        return [
            'driver' => 'SQL Server (FreeTDS)',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $this->databaseUser,
            'table' => 'E075PRO',
            'credentials_configured' => $this->isConfigured(),
        ];
    }

    /** @return list<string> */
    public function availableProductColumns(): array
    {
        if (null !== $this->productColumns) {
            return $this->productColumns;
        }

        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $statement = $this->connection()->query(<<<'SQL'
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = 'E075PRO'
                ORDER BY ORDINAL_POSITION
                SQL);
            $columns = $statement->fetchAll(\PDO::FETCH_COLUMN);

            return $this->productColumns = array_values(array_filter(array_map('strval', $columns)));
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to inspect the Senior product table.', ['exception' => $exception]);

            return [];
        }
    }

    /** @return array{tables: list<string>, error: string|null} */
    public function availableTables(): array
    {
        if (!$this->isConfigured()) {
            return ['tables' => [], 'error' => 'A conexão Senior ainda não foi configurada.'];
        }

        try {
            $rows = $this->connection()->query(<<<'SQL'
                SELECT TABLE_SCHEMA, TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_SCHEMA, TABLE_NAME
                SQL)->fetchAll(\PDO::FETCH_ASSOC);
            $tables = array_map(static fn (array $row): string => sprintf('%s.%s', $row['TABLE_SCHEMA'], $row['TABLE_NAME']), $rows);

            return ['tables' => $tables, 'error' => null];
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to inspect Senior tables.', ['exception' => $exception]);

            return ['tables' => [], 'error' => 'Não foi possível listar as tabelas da base Senior.'];
        }
    }

    /** @return array{columns: list<string>, error: string|null} */
    public function columnsForTable(string $table): array
    {
        [$schema, $tableName] = $this->splitTable($table);
        if ('' === $tableName) {
            return ['columns' => [], 'error' => 'Selecione uma tabela válida.'];
        }

        try {
            $statement = $this->connection()->prepare(<<<'SQL'
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = :table AND (:schema = '' OR TABLE_SCHEMA = :schema)
                ORDER BY ORDINAL_POSITION
                SQL);
            $statement->execute(['table' => $tableName, 'schema' => $schema]);

            return ['columns' => array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN))), 'error' => null];
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to inspect Senior table columns.', ['exception' => $exception]);

            return ['columns' => [], 'error' => 'Não foi possível listar os campos da tabela Senior.'];
        }
    }

    /** @return array{rows: list<array<string, mixed>>, error: string|null} */
    public function rowsForTable(string $table, array $columns, int $limit = 30): array
    {
        [$schema, $tableName] = $this->splitTable($table);
        $columns = array_values(array_filter($columns, static fn (mixed $column): bool => is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1));
        if ('' === $tableName || [] === $columns) return ['rows' => [], 'error' => 'Selecione a tabela e os campos de usuários.'];
        try {
            $quotedColumns = implode(', ', array_map(static fn (string $column): string => sprintf('[%s]', $column), $columns));
            $qualifiedTable = '' === $schema ? sprintf('[%s]', $tableName) : sprintf('[%s].[%s]', $schema, $tableName);
            $rows = $this->connection()->query(sprintf('SELECT TOP %d %s FROM %s', max(1, min(100, $limit)), $quotedColumns, $qualifiedTable))->fetchAll(\PDO::FETCH_ASSOC);
            return ['rows' => $rows, 'error' => null];
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to inspect Senior users.', ['exception' => $exception]);
            return ['rows' => [], 'error' => 'Não foi possível consultar os usuários da base Senior.'];
        }
    }

    /**
     * @param array<string, string> $mapping
     * @return array{configured: bool, products: list<array<string, mixed>>, error: string|null, sourceTable: string, recordCount: int, page: int, perPage: int, pageCount: int, ncmUpdateAvailable: bool}
     */
    public function listProducts(array $mapping = [], int $page = 1, int $perPage = 10): array
    {
        if (!$this->isConfigured()) {
            return $this->emptyResult(false);
        }

        try {
            $columns = $this->columnLookup();
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
            $connection = $this->connection();
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

    private function connection(): \PDO
    {
        return new \PDO($this->databaseDsn, $this->databaseUser, $this->databasePassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function splitTable(string $table): array
    {
        $parts = array_values(array_filter(explode('.', trim($table)), static fn (string $part): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) === 1));

        return 2 === count($parts) ? [$parts[0], $parts[1]] : ['', $parts[0] ?? ''];
    }

    /** @return array<string, string> */
    private function columnLookup(): array
    {
        $lookup = [];
        foreach ($this->availableProductColumns() as $column) {
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
