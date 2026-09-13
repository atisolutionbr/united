<?php

namespace App\Service;

use App\Entity\ErpConnection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Executes only the operation and columns selected in a saved, company-scoped binding. */
final class ProcessGateway
{
    public function __construct(private readonly DatabaseSchemaInspector $schema, private readonly ConnectionSecretCipher $cipher, private readonly HttpClientInterface $http) {}

    public static function identifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/D', $value)) throw new \InvalidArgumentException('Tabela ou coluna inválida.');
        return $value;
    }

    public function validate(ErpConnection $connection, array $config): array
    {
        if (!in_array($config['method'] ?? '', ['database', 'api', 'webservice'], true)) throw new \InvalidArgumentException('Selecione BD, API ou WebService.');
        if (!in_array($config['purpose'] ?? '', ['create', 'approval'], true)) throw new \InvalidArgumentException('Selecione a finalidade do vínculo.');
        $config['mapping'] = array_filter($config['mapping'] ?? [], static fn ($v) => is_string($v) && '' !== trim($v));
        if ('create' === ($config['purpose'] ?? '') && count(array_unique($config['mapping'])) !== count($config['mapping'])) throw new \InvalidArgumentException('Cada campo de destino deve receber apenas uma informação.');
        if (!$config['mapping']) throw new \InvalidArgumentException('Vincule pelo menos um campo.');
        $settings = $connection->getSettingsForMethod($config['method']);
        if ('database' === $config['method']) {
            self::identifier($config['table'] ?? '');
            $tables = $this->schema->tables($settings);
            if (!in_array($config['table'], $tables['tables'], true)) throw new \InvalidArgumentException('Selecione uma tabela existente no banco configurado.');
            $columns = $this->schema->columns($settings, $config['table'])['columns'];
            $check = array_values($config['mapping']);
            if ('approval' === $config['purpose']) $check = [...$check, ...self::keys($config), $config['status_column'] ?? ''];
            foreach ($check as $column) if (!in_array(self::identifier($column), $columns, true)) throw new \InvalidArgumentException('Coluna não encontrada: '.$column);
        } else {
            if (!filter_var($settings['endpoint'] ?? '', FILTER_VALIDATE_URL) || !in_array(parse_url($settings['endpoint'], PHP_URL_SCHEME), ['http', 'https'], true)) throw new \InvalidArgumentException('Salve primeiro o endereço HTTP(S) da conexão.');
            foreach ($config['mapping'] as $path) self::pathParts($path);
            foreach ('approval' === $config['purpose'] ? ['list_operation', 'decision_operation', 'items_path', 'total_path', 'key_columns', 'status_column'] : ['create_operation'] as $key) {
                if ('' === trim($config[$key] ?? '')) throw new \InvalidArgumentException('Preencha '.$key.' no vínculo.');
            }
            if ('' === trim($config['success_path'] ?? '') || '' === trim($config['success_value'] ?? '')) throw new \InvalidArgumentException('Configure o campo e o valor de confirmação do ERP.');
        }
        if ('approval' === $config['purpose']) {
            self::keys($config);
            $statuses = [$config['pending_value'] ?? '', $config['approved_value'] ?? '', $config['rejected_value'] ?? ''];
            if (in_array('', $statuses, true) || 3 !== count(array_unique($statuses))) throw new \InvalidArgumentException('Informe códigos diferentes para pendente, aprovado e reprovado.');
        }
        return $config;
    }

    public static function keys(array $config): array
    {
        $keys = array_values(array_filter(array_map('trim', explode(',', $config['key_columns'] ?? ''))));
        if (!$keys || count($keys) > 8) throw new \InvalidArgumentException('Informe a chave completa do objeto (até oito campos).');
        return $keys;
    }

    public function list(ErpConnection $connection, array $config, int $page): array
    {
        $offset = (max(1, $page) - 1) * 15;
        if ('database' !== $config['method']) {
            $response = $this->remote($connection, $config, 'list_operation', ['page' => max(1, $page), 'page_size' => 15, 'status' => $config['pending_value']]);
            $items = self::readPath($response, $config['items_path']);
            $total = self::readPath($response, $config['total_path']);
            if (!is_array($items) || !is_numeric($total) || count($items) > 15) throw new \RuntimeException('O ERP deve retornar a coleção paginada (máximo 15) e o total configurados.');
            $rows = [];
            foreach ($items as $item) {
                $keys = [];
                foreach (self::keys($config) as $key) $keys[$key] = self::readPath($item, $key);
                $data = [];
                foreach ($config['mapping'] as $key => $path) $data[$key] = self::readPath($item, $path);
                $rows[] = ['keys' => $keys, 'data' => $data];
            }
            return ['rows' => $rows, 'total' => (int) $total];
        }
        [$pdo, $quote, $table] = $this->database($connection, $config);
        $where = $quote($config['status_column']).' = ?';
        $count = $pdo->prepare('SELECT COUNT(*) FROM '.$table.' WHERE '.$where);
        $count->execute([$config['pending_value']]);
        $columns = array_values(array_unique([...self::keys($config), ...array_values($config['mapping'])]));
        $sql = 'SELECT '.implode(', ', array_map($quote, $columns)).' FROM '.$table.' WHERE '.$where.' ORDER BY '.implode(', ', array_map($quote, self::keys($config)));
        $driver = $connection->getSettingsForMethod('database')['driver'] ?? 'sqlserver';
        $sql .= in_array($driver, ['mysql', 'mariadb', 'postgresql'], true) ? ' LIMIT 15 OFFSET '.$offset : ' OFFSET '.$offset.' ROWS FETCH NEXT 15 ROWS ONLY';
        $statement = $pdo->prepare($sql);
        $statement->execute([$config['pending_value']]);
        $rows = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $keys = array_intersect_key($row, array_flip(self::keys($config)));
            $data = [];
            foreach ($config['mapping'] as $key => $column) $data[$key] = $row[$column] ?? null;
            $rows[] = ['keys' => $keys, 'data' => $data];
        }
        return ['rows' => $rows, 'total' => (int) $count->fetchColumn()];
    }

    public function decide(ErpConnection $connection, array $config, array $keys, string $decision, string $requestKey): string
    {
        if (!in_array($decision, ['approve', 'reject'], true)) throw new \InvalidArgumentException('Decisão inválida.');
        if (array_diff(self::keys($config), array_keys($keys)) || count($keys) !== count(self::keys($config))) throw new \InvalidArgumentException('A chave completa do registro é obrigatória.');
        foreach ($keys as $value) if (!is_scalar($value) || '' === (string) $value) throw new \InvalidArgumentException('Chave inválida.');
        $status = $config['approve' === $decision ? 'approved_value' : 'rejected_value'];
        if ('database' !== $config['method']) {
            $payload = [];
            foreach ($keys as $path => $value) self::putPath($payload, $path, $value);
            self::putPath($payload, $config['status_column'], $status);
            $payload['expected_status'] = $config['pending_value'];
            return $this->confirmed($this->remote($connection, $config, 'decision_operation', $payload, $requestKey), $config);
        }
        [$pdo, $quote, $table] = $this->database($connection, $config);
        $where = [];
        foreach ($keys as $column => $value) $where[] = $quote($column).' = ?';
        $where[] = $quote($config['status_column']).' = ?';
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('UPDATE '.$table.' SET '.$quote($config['status_column']).' = ? WHERE '.implode(' AND ', $where));
            $statement->execute([$status, ...array_values($keys), $config['pending_value']]);
            if (1 !== $statement->rowCount()) throw new \RuntimeException('O registro foi alterado por outro usuário ou a chave não é única. Nenhuma alteração confirmada.');
            $pdo->commit();
            return 'ERP confirmou a alteração de um registro.';
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    public function create(ErpConnection $connection, array $config, array $data, string $requestKey): string
    {
        if ('create' !== $config['purpose']) throw new \InvalidArgumentException('Selecione um vínculo de envio.');
        $payload = [];
        foreach ($config['mapping'] as $key => $target) {
            if (!array_key_exists($key, $data)) continue;
            if ('database' === $config['method']) $payload[$target] = $data[$key];
            else self::putPath($payload, $target, $data[$key]);
        }
        if (!$payload) throw new \InvalidArgumentException('Não há campos vinculados para envio.');
        if ('database' !== $config['method']) return $this->confirmed($this->remote($connection, $config, 'create_operation', $payload, $requestKey), $config);
        [$pdo, $quote, $table] = $this->database($connection, $config);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('INSERT INTO '.$table.' ('.implode(', ', array_map($quote, array_keys($payload))).') VALUES ('.implode(', ', array_fill(0, count($payload), '?')).')');
            $statement->execute(array_values($payload));
            if (1 !== $statement->rowCount()) throw new \RuntimeException('O ERP não confirmou a inclusão de um registro.');
            $pdo->commit();
            return 'ERP confirmou a inclusão do registro.';
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    private function database(ErpConnection $connection, array $config): array
    {
        $settings = $connection->getSettingsForMethod('database');
        $driver = $settings['driver'] ?? 'sqlserver';
        $quote = static function (string $column) use ($driver): string {
            self::identifier($column);
            return implode('.', array_map(static fn ($part) => match ($driver) { 'sqlserver' => '['.$part.']', 'mysql', 'mariadb' => '`'.$part.'`', default => '"'.$part.'"' }, explode('.', $column)));
        };
        return [$this->schema->open($settings), $quote, $quote($config['table'])];
    }

    public function lookup(ErpConnection $connection, array $source, string $term, int $page): array
    {
        $config = $source + ['list_operation' => $source['operation'] ?? ''];
        $response = $this->remote($connection, $config, 'list_operation', [($source['search_param'] ?? '') ?: 'q' => $term, 'page' => max(1, $page), 'page_size' => 25]);
        $rows = self::readPath($response, $source['items_path'] ?? 'data.items');
        if (!is_array($rows) || count($rows) > 25) throw new \RuntimeException('A origem deve devolver uma coleção paginada de até 25 itens.');
        $items = [];
        foreach ($rows as $row) {
            $value = self::readPath($row, $source['value']); $label = self::readPath($row, $source['label']);
            if (!is_scalar($value) || !is_scalar($label)) continue;
            $items[] = ['value' => (string) $value, 'label' => (string) $label, 'barcode' => !empty($source['barcode']) ? (string) self::readPath($row, $source['barcode']) : '', 'company' => ''];
        }
        return ['items' => $items, 'more' => count($rows) === 25];
    }

    private function remote(ErpConnection $connection, array $config, string $operation, array $payload, string $requestKey = ''): array
    {
        $settings = $connection->getSettingsForMethod($config['method']);
        $endpoint = $settings['endpoint'] ?? '';
        if (!in_array(parse_url($endpoint, PHP_URL_SCHEME), ['http', 'https'], true)) throw new \InvalidArgumentException('Endpoint HTTP(S) obrigatório.');
        if ('webservice' === $config['method']) {
            if (!class_exists(\SoapClient::class)) throw new \RuntimeException('Extensão SOAP indisponível.');
            $client = new \SoapClient($endpoint, ['exceptions' => true, 'connection_timeout' => 15, 'cache_wsdl' => WSDL_CACHE_NONE, 'login' => $settings['username'] ?? '', 'password' => $this->cipher->decrypt($settings['password_encrypted'] ?? ''), 'stream_context' => stream_context_create(['http' => ['timeout' => 25]])]);
            if ($requestKey) $payload['request_id'] = $requestKey;
            foreach (['soap_user_path' => ($settings['username'] ?? ''), 'soap_password_path' => $this->cipher->decrypt($settings['password_encrypted'] ?? '')] as $key => $value) {
                if (!empty($config[$key])) self::putPath($payload, $config[$key], $value);
            }
            $arguments = 'named' === ($config['soap_style'] ?? '') ? array_map(static fn ($value, $key) => new \SoapParam($value, $key), array_values($payload), array_keys($payload)) : [$payload];
            $response = $client->__soapCall($config[$operation], $arguments);
            return json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR) ?? [];
        }
        $path = $config[$operation] ?? '';
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '://')) throw new \InvalidArgumentException('Use uma rota relativa iniciada por / no servidor configurado.');
        $headers = ['Accept' => 'application/json'];
        $token = $this->cipher->decrypt($settings['token_encrypted'] ?? '');
        if ($token) $headers['api_key' === ($settings['authentication'] ?? '') ? ($settings['api_key_name'] ?: 'Authorization') : 'Authorization'] = 'api_key' === ($settings['authentication'] ?? '') ? $token : 'Bearer '.$token;
        if ('oauth_client' === ($settings['authentication'] ?? '') && !$token) throw new \RuntimeException('Configure um token OAuth válido na conexão antes do envio.');
        if ($requestKey) $headers['Idempotency-Key'] = $requestKey;
        $options = ['headers' => $headers, 'timeout' => 25, 'max_duration' => 30, 'max_redirects' => 0];
        $options['list_operation' === $operation ? 'query' : 'json'] = $payload;
        $response = $this->http->request('list_operation' === $operation ? 'GET' : 'POST', rtrim($endpoint, '/').$path, $options);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) throw new \RuntimeException('O ERP respondeu HTTP '.$response->getStatusCode().'.');
        return $response->toArray(false);
    }

    private function confirmed(array $response, array $config): string
    {
        $actual = self::readPath($response, $config['success_path']);
        $actual = is_bool($actual) ? ($actual ? 'true' : 'false') : (string) $actual;
        if ($actual !== $config['success_value']) throw new \RuntimeException('O retorno do ERP não confirmou o resultado esperado. Confira no ERP antes de reenviar.');
        return 'ERP confirmou o resultado configurado.';
    }

    private static function pathParts(string $path): array
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)*$/D', $path)) throw new \InvalidArgumentException('Caminho de propriedade inválido.');
        return explode('.', $path);
    }

    public static function readPath(mixed $data, string $path): mixed
    {
        foreach (self::pathParts($path) as $part) { if (!is_array($data) || !array_key_exists($part, $data)) return null; $data = $data[$part]; }
        return $data;
    }

    public static function putPath(array &$data, string $path, mixed $value): void
    {
        $target = &$data;
        foreach (self::pathParts($path) as $part) { if (isset($target[$part]) && !is_array($target[$part])) throw new \InvalidArgumentException('Mapeamentos de propriedades em conflito.'); $target = &$target[$part]; }
        $target = $value;
    }
}
