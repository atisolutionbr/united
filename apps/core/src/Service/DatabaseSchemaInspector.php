<?php

namespace App\Service;

final class DatabaseSchemaInspector
{
    public function __construct(private readonly ConnectionSecretCipher $secretCipher)
    {
    }

    /** @param array<string, mixed> $settings
     *  @return array{tables: list<string>, error: string|null}
     */
    public function tables(array $settings): array
    {
        try {
            $rows = $this->open($settings)->query(<<<'SQL'
                SELECT TABLE_SCHEMA, TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_SCHEMA, TABLE_NAME
                SQL)->fetchAll(\PDO::FETCH_ASSOC);

            $tables = [];
            foreach ($rows as $row) {
                $schema = (string) ($row['TABLE_SCHEMA'] ?? '');
                $table = (string) ($row['TABLE_NAME'] ?? '');
                if ('' !== $table) {
                    $tables[] = '' !== $schema ? $schema.'.'.$table : $table;
                }
            }

            return ['tables' => $tables, 'error' => null];
        } catch (\Throwable $exception) {
            return ['tables' => [], 'error' => 'Não foi possível conectar ao banco externo. Revise IP/DNS, porta, banco, usuário e senha.'];
        }
    }

    /** @param array<string, mixed> $settings
     *  @return array{columns: list<string>, error: string|null}
     */
    public function columns(array $settings, string $table): array
    {
        [$schema, $tableName] = $this->splitTable($table);
        if ('' === $tableName) {
            return ['columns' => [], 'error' => 'Selecione uma tabela válida.'];
        }

        try {
            $statement = $this->open($settings)->prepare(<<<'SQL'
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = :table AND (:schema = '' OR TABLE_SCHEMA = :schema)
                ORDER BY ORDINAL_POSITION
                SQL);
            $statement->execute(['table' => $tableName, 'schema' => $schema]);

            return ['columns' => array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN))), 'error' => null];
        } catch (\Throwable $exception) {
            return ['columns' => [], 'error' => 'Não foi possível listar os campos da tabela selecionada.'];
        }
    }

    /** @return array{rows: list<array<string, mixed>>, error: string|null} */
    public function rows(array $settings, string $table, array $columns, int $limit = 30): array
    {
        [$schema, $tableName] = $this->splitTable($table);
        $columns = array_values(array_filter($columns, static fn (mixed $column): bool => is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1));
        if ('' === $tableName || [] === $columns) return ['rows' => [], 'error' => 'Selecione a tabela e os campos de usuários.'];
        try {
            $quotedColumns = implode(', ', array_map(static fn (string $column): string => sprintf('[%s]', $column), $columns));
            $qualifiedTable = '' === $schema ? sprintf('[%s]', $tableName) : sprintf('[%s].[%s]', $schema, $tableName);
            $statement = $this->open($settings)->query(sprintf('SELECT TOP %d %s FROM %s', max(1, min(100, $limit)), $quotedColumns, $qualifiedTable));
            return ['rows' => $statement->fetchAll(\PDO::FETCH_ASSOC), 'error' => null];
        } catch (\Throwable $exception) {
            return ['rows' => [], 'error' => 'Não foi possível consultar os usuários da origem selecionada.'];
        }
    }

    /** @param array<string, mixed> $settings */
    public function isConfigured(array $settings): bool
    {
        return '' !== trim((string) ($settings['host'] ?? ''))
            && '' !== trim((string) ($settings['database'] ?? ''))
            && '' !== trim((string) ($settings['username'] ?? ''))
            && '' !== $this->secretCipher->decrypt((string) ($settings['password_encrypted'] ?? ''));
    }

    /** @param array<string, mixed> $settings */
    public function open(array $settings): \PDO
    {
        $driver = strtolower((string) ($settings['driver'] ?? 'sql server'));
        $host = trim((string) ($settings['host'] ?? ''));
        $port = trim((string) ($settings['port'] ?? ''));
        $database = trim((string) ($settings['database'] ?? ''));
        $username = (string) ($settings['username'] ?? '');
        $password = $this->secretCipher->decrypt((string) ($settings['password_encrypted'] ?? ''));

        if ('' === $host || '' === $database || '' === $username || '' === $password) {
            throw new \RuntimeException('Configuração de banco incompleta.');
        }

        $dsn = str_contains($driver, 'postgres')
            ? sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, '' !== $port ? $port : '5432', $database)
            : sprintf('dblib:host=%s%s;dbname=%s', $host, '' !== $port ? ':'.$port : '', $database);

        return new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 4,
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function splitTable(string $table): array
    {
        $parts = array_values(array_filter(explode('.', trim($table)), static fn (string $part): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) === 1));

        return 2 === count($parts) ? [$parts[0], $parts[1]] : ['', $parts[0] ?? ''];
    }
}
