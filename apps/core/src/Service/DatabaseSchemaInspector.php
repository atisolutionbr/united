<?php

namespace App\Service;

final class DatabaseSchemaInspector
{
    private array $connections = [];
    public function __construct(private readonly ConnectionSecretCipher $secretCipher, private readonly ?\Psr\Cache\CacheItemPoolInterface $cache = null, private readonly ?\Symfony\Component\HttpFoundation\RequestStack $requests = null) {}

    public function releaseSession(): void
    {
        $request = $this->requests?->getCurrentRequest();
        if ($request?->hasSession() && $request->getSession()->isStarted()) $request->getSession()->save();
    }

    private function connectionKey(array $settings): string
    {
        return hash('sha256', json_encode(array_intersect_key($settings, array_flip(['driver','host','port','database','username','password_encrypted']))));
    }

    private function metadata(string $kind, array $settings, callable $load): array
    {
        $item = $this->cache?->getItem('united.schema.'.hash('sha256', $kind.$this->connectionKey($settings)));
        if ($item?->isHit()) return $item->get();
        $result = $load();
        if ($item) { $item->set($result)->expiresAfter(empty($result['error']) ? 300 : 10); $this->cache->save($item); }
        return $result;
    }

    /** @param array<string, mixed> $settings
     * @return array{tables: list<string>, error: string|null}
     */
    public function tables(array $settings): array
    {
        return $this->metadata('tables', $settings, fn () => $this->loadTables($settings));
    }

    private function loadTables(array $settings): array
    {
        try {
            $rows = $this->open($settings)->query($this->tableQuery($this->driver($settings)))->fetchAll(\PDO::FETCH_ASSOC);
            $tables = [];
            foreach ($rows as $row) {
                $schema = trim((string) ($row['table_schema'] ?? $row['TABLE_SCHEMA'] ?? ''));
                $table = trim((string) ($row['table_name'] ?? $row['TABLE_NAME'] ?? ''));
                if ('' !== $table) $tables[] = '' !== $schema ? $schema.'.'.$table : $table;
            }
            return ['tables' => array_values(array_unique($tables)), 'error' => null];
        } catch (\Throwable $exception) {
            return ['tables' => [], 'error' => $this->message($exception, 'Não foi possível conectar ao banco externo. Revise IP/DNS, porta, banco, usuário e senha.')];
        }
    }

    /** @param array<string, mixed> $settings
     * @return array{columns: list<string>, error: string|null}
     */
    public function columns(array $settings, string $table): array
    {
        return $this->metadata('columns.'.$table, $settings, fn () => $this->loadColumns($settings, $table));
    }

    private function loadColumns(array $settings, string $table): array
    {
        [$schema, $tableName] = $this->splitTable($table);
        if ('' === $tableName) return ['columns' => [], 'error' => 'Selecione uma tabela válida.'];
        try {
            $driver = $this->driver($settings);
            $statement = $this->open($settings)->prepare($this->columnQuery($driver));
            $statement->execute($this->columnParameters($driver, $schema, $tableName));
            return ['columns' => array_values(array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN))), 'error' => null];
        } catch (\Throwable $exception) {
            return ['columns' => [], 'error' => $this->message($exception, 'Não foi possível listar os campos da tabela selecionada.')];
        }
    }

    /** @return array{rows: list<array<string, mixed>>, error: string|null} */
    public function rows(array $settings, string $table, array $columns, int $limit = 30): array
    {
        [$schema, $tableName] = $this->splitTable($table);
        $columns = array_values(array_filter($columns, static fn (mixed $column): bool => is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1));
        if ('' === $tableName || [] === $columns) return ['rows' => [], 'error' => 'Selecione a tabela e os campos de usuários.'];
        try {
            $driver = $this->driver($settings);
            $quotedColumns = implode(', ', array_map(fn (string $column): string => $this->quote($driver, $column), $columns));
            $qualifiedTable = '' === $schema ? $this->quote($driver, $tableName) : $this->quote($driver, $schema).'.'.$this->quote($driver, $tableName);
            $safeLimit = max(1, min(100, $limit));
            $sql = match ($driver) {
                'sqlserver' => sprintf('SELECT TOP %d %s FROM %s', $safeLimit, $quotedColumns, $qualifiedTable),
                'oracle' => sprintf('SELECT %s FROM %s FETCH FIRST %d ROWS ONLY', $quotedColumns, $qualifiedTable, $safeLimit),
                default => sprintf('SELECT %s FROM %s LIMIT %d', $quotedColumns, $qualifiedTable, $safeLimit),
            };
            return ['rows' => $this->open($settings)->query($sql)->fetchAll(\PDO::FETCH_ASSOC), 'error' => null];
        } catch (\Throwable $exception) {
            return ['rows' => [], 'error' => $this->message($exception, 'Não foi possível consultar os usuários da origem selecionada.')];
        }
    }

    /** @param array<string, mixed> $settings */
    public function isConfigured(array $settings): bool
    {
        return '' !== trim((string) ($settings['host'] ?? '')) && '' !== trim((string) ($settings['database'] ?? '')) && '' !== trim((string) ($settings['username'] ?? '')) && '' !== $this->secretCipher->decrypt((string) ($settings['password_encrypted'] ?? ''));
    }

    /** @param array<string, mixed> $settings
     * @return array{connected: bool, message: string}
     */
    public function test(array $settings): array
    {
        try {
            $count = (int) $this->open($settings)->query($this->countQuery($this->driver($settings)))->fetchColumn();
            return ['connected' => true, 'message' => sprintf('Conexão direta estabelecida com sucesso. %d tabelas disponíveis para vínculo.', $count)];
        } catch (\Throwable $exception) {
            return ['connected' => false, 'message' => $this->message($exception, 'Não foi possível validar o acesso às tabelas. Revise IP/DNS, porta, banco, usuário e senha.')];
        }
    }

    /** @param array<string, mixed> $settings */
    public function open(array $settings): \PDO
    {
        $this->releaseSession();
        $key = $this->connectionKey($settings);
        if (isset($this->connections[$key])) return $this->connections[$key];
        $failure = $this->cache?->getItem('united.database.failure.'.$key);
        if ($failure?->isHit()) throw new \PDOException('ERP temporariamente indisponível; nova tentativa em até 20 segundos.', 20009);
        $driver = $this->driver($settings);
        $host = trim((string) ($settings['host'] ?? ''));
        $port = trim((string) ($settings['port'] ?? ''));
        $database = trim((string) ($settings['database'] ?? ''));
        $username = (string) ($settings['username'] ?? '');
        $password = $this->secretCipher->decrypt((string) ($settings['password_encrypted'] ?? ''));
        if ('' === $host || '' === $database || '' === $username || '' === $password) throw new \RuntimeException('Configuração de banco incompleta.');
        $pdoDriver = match ($driver) {'sqlserver' => 'dblib', 'postgresql' => 'pgsql', 'mysql', 'mariadb' => 'mysql', 'oracle' => 'oci', 'firebird' => 'firebird', default => throw new \RuntimeException('O conector MongoDB requer a extensao MongoDB do servidor da plataforma.')};
        if (!in_array($pdoDriver, \PDO::getAvailableDrivers(), true)) throw new \RuntimeException(sprintf('O driver %s precisa ser habilitado no servidor da plataforma.', strtoupper($pdoDriver)));
        $dsn = match ($driver) {
            'sqlserver' => sprintf('dblib:host=%s%s;dbname=%s;version=7.4', $host, '' !== $port ? ':'.$port : '', $database),
            'postgresql' => sprintf('pgsql:host=%s;port=%s;dbname=%s;connect_timeout=3', $host, '' !== $port ? $port : '5432', $database),
            'mysql', 'mariadb' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, '' !== $port ? $port : '3306', $database),
            'oracle' => sprintf('oci:dbname=//%s:%s/%s;charset=AL32UTF8', $host, '' !== $port ? $port : '1521', $database),
            'firebird' => sprintf('firebird:dbname=%s/%s:%s;charset=UTF8', $host, '' !== $port ? $port : '3050', $database),
        };
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3];
        if ('dblib' === $pdoDriver) {
            $options[\PDO::DBLIB_ATTR_CONNECTION_TIMEOUT] = 3;
            $options[\PDO::DBLIB_ATTR_QUERY_TIMEOUT] = 8;
        }
        try {
            $pdo = new \PDO($dsn, $username, $password, $options);
            if ('pgsql' === $pdoDriver) $pdo->exec('SET statement_timeout = 8000');
            return $this->connections[$key] = $pdo;
        } catch (\PDOException $e) {
            if ($failure) { $failure->set(true)->expiresAfter(20); $this->cache->save($failure); }
            throw $e;
        }
    }

    /** @param array<string, mixed> $settings */
    private function driver(array $settings): string
    {
        return match (strtolower(trim((string) ($settings['driver'] ?? 'sqlserver')))) {
            'sql server', 'sqlserver', 'mssql' => 'sqlserver', 'postgres', 'postgresql' => 'postgresql', 'mysql' => 'mysql', 'mariadb' => 'mariadb', 'oracle' => 'oracle', 'firebird' => 'firebird', 'mongodb', 'mongo' => 'mongodb', default => throw new \RuntimeException('Driver de banco não suportado.'),
        };
    }

    private function tableQuery(string $driver): string
    {
        return match ($driver) {
            'oracle' => 'SELECT USER AS table_schema, TABLE_NAME AS table_name FROM USER_TABLES ORDER BY TABLE_NAME',
            'firebird' => 'SELECT NULL AS table_schema, TRIM(RDB$RELATION_NAME) AS table_name FROM RDB$RELATIONS WHERE COALESCE(RDB$SYSTEM_FLAG, 0) = 0 AND RDB$VIEW_BLR IS NULL ORDER BY RDB$RELATION_NAME',
            'mysql', 'mariadb' => "SELECT TABLE_SCHEMA AS table_schema, TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = DATABASE() ORDER BY TABLE_SCHEMA, TABLE_NAME",
            default => "SELECT TABLE_SCHEMA AS table_schema, TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_SCHEMA, TABLE_NAME",
        };
    }

    private function countQuery(string $driver): string
    {
        return match ($driver) {
            'oracle' => 'SELECT COUNT(*) FROM USER_TABLES', 'firebird' => 'SELECT COUNT(*) FROM RDB$RELATIONS WHERE COALESCE(RDB$SYSTEM_FLAG, 0) = 0 AND RDB$VIEW_BLR IS NULL', 'mysql', 'mariadb' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = DATABASE()", default => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'",
        };
    }

    private function columnQuery(string $driver): string
    {
        return match ($driver) {
            'oracle' => 'SELECT COLUMN_NAME FROM USER_TAB_COLUMNS WHERE TABLE_NAME = UPPER(:table) ORDER BY COLUMN_ID',
            'firebird' => 'SELECT TRIM(RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS WHERE RDB$RELATION_NAME = UPPER(:table) ORDER BY RDB$FIELD_POSITION',
            'mysql', 'mariadb' => 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND TABLE_SCHEMA = DATABASE() ORDER BY ORDINAL_POSITION',
            default => "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND (:schema = '' OR TABLE_SCHEMA = :schema) ORDER BY ORDINAL_POSITION",
        };
    }

    /** @return array<string, string> */
    private function columnParameters(string $driver, string $schema, string $table): array
    {
        return in_array($driver, ['oracle', 'firebird', 'mysql', 'mariadb'], true) ? ['table' => $table] : ['table' => $table, 'schema' => $schema];
    }

    private function quote(string $driver, string $identifier): string
    {
        return match ($driver) {'sqlserver' => '['.$identifier.']', 'mysql', 'mariadb' => '`'.$identifier.'`', default => '"'.$identifier.'"'};
    }

    public static function queryFailure(\Throwable $exception): string
    {
        $code = (string) ($exception instanceof \PDOException ? ($exception->errorInfo[1] ?? $exception->getCode()) : $exception->getCode());
        return match ($code) {
            '20009', '20002', '08001', '08006', '08003' => 'O servidor do banco não está acessível. Verifique se o serviço SQL está iniciado e se o endereço e a porta estão disponíveis. Os vínculos foram preservados.',
            '18456', '28000', '28P01' => 'O banco recusou a autenticação. Confira as credenciais da conexão; os vínculos foram preservados.',
            '208', '42P01', '42S02' => 'A tabela vinculada não foi encontrada ou não está acessível ao usuário da conexão.',
            '207', '42703', '42S22' => 'Uma coluna vinculada não foi encontrada. Confira os campos da tabela selecionada.',
            default => 'Não foi possível concluir a consulta ao ERP. Confira a conexão, a tabela e os campos vinculados. Os vínculos foram preservados.',
        };
    }

    private function message(\Throwable $exception, string $fallback): string
    {
        $message = $exception->getMessage();
        return str_starts_with($message, 'O driver ') || str_starts_with($message, 'O conector ') || str_starts_with($message, 'Driver de banco') || str_starts_with($message, 'Configuração de banco') ? $message : $fallback;
    }

    /** @return array{0: string, 1: string} */
    private function splitTable(string $table): array
    {
        $parts = array_values(array_filter(explode('.', trim($table)), static fn (string $part): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) === 1));
        return 2 === count($parts) ? [$parts[0], $parts[1]] : ['', $parts[0] ?? ''];
    }
}
