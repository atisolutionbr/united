<?php

namespace App\Service;

use App\Entity\ErpConnection;

final class LookupCatalog
{
    public const FIELDS = ['product', 'requester', 'project', 'phase', 'warehouse', 'cost_center'];
    public function __construct(private readonly DatabaseSchemaInspector $schema, private readonly ProcessGateway $gateway) {}

    public function source(ErpConnection $connection, string $form, string $field): array
    {
        if ('product' === $field) $form = 'products';
        foreach (['database', 'api', 'webservice'] as $method) {
            $source = $connection->getSettingsForMethod($method)['lookup_sources'][$form][$field] ?? [];
            if (!empty($source['enabled'])) return $source + ['method' => $method];
        }
        if ('product' === $field) {
            foreach (['database', 'api', 'webservice'] as $method) {
                $settings = $connection->getSettingsForMethod($method);
                $custom = $settings['lookup_sources']['products']['product'] ?? [];
                if (!empty($custom['enabled'])) return $custom + ['method' => $method];
            }
            $binding = FormBindingRegistry::resolve($connection->getSettingsForMethod('database'), 'products', $connection->getProductMapping());
            if (!empty($binding['table'])) return ['method' => 'database', 'table' => $binding['table'], 'value' => $binding['mapping']['product_code'] ?? '', 'label' => $binding['mapping']['product_name'] ?? '', 'barcode' => $binding['mapping']['barcode'] ?? '', 'company_column' => $binding['mapping']['company'] ?? ''];
        }
        throw new \InvalidArgumentException('Configure a origem da lista deste campo em Vínculos de listas.');
    }

    public function search(ErpConnection $connection, string $form, string $field, string $term, int $page, ?string $company = null): array
    {
        $source = $this->source($connection, $form, $field);
        if ('database' !== $source['method']) return $this->gateway->lookup($connection, $source, $term, $page);
        $settings = $connection->getSettingsForMethod('database');
        $driver = strtolower($settings['driver'] ?? 'sqlserver');
        $quote = static function (string $name) use ($driver): string {
            ProcessGateway::identifier($name);
            return implode('.', array_map(static fn ($p) => match ($driver) { 'sqlserver', 'sql server', 'mssql' => '['.$p.']', 'mysql', 'mariadb' => '`'.$p.'`', default => '"'.$p.'"' }, explode('.', $name)));
        };
        $table = $quote($source['table']);
        $metadata = $this->schema->columns($settings, $source['table']);
        if ($metadata['error']) throw new \RuntimeException('Não foi possível consultar a origem vinculada. Confira a conexão do ERP e tente novamente.');
        $columns = $metadata['columns'];
        $names = array_combine(array_map('strtolower', $columns), $columns);
        foreach (['value', 'label', 'barcode', 'company_column'] as $key) if (!empty($source[$key])) $source[$key] = $names[strtolower($source[$key])] ?? $source[$key];
        foreach (['value', 'label'] as $key) if (empty($source[$key]) || !in_array($source[$key], $columns, true)) throw new \InvalidArgumentException('Vincule código e nome para habilitar a lista pesquisável.');
        $selected = array_values(array_unique(array_filter([$source['value'], $source['label'], $source['barcode'] ?? '', $source['company_column'] ?? ''])));
        foreach ($selected as $column) if (!in_array($column, $columns, true)) throw new \InvalidArgumentException('Revise as colunas da lista: '.$column);
        $where = []; $params = [];
        if ('' !== $term) {
            $searchColumns = array_values(array_filter([$source['value'], $source['label'], $source['barcode'] ?? '']));
            foreach ($searchColumns as $column) {
                $cast = match ($driver) { 'sqlserver', 'sql server', 'mssql' => 'NVARCHAR(4000)', 'mysql', 'mariadb' => 'CHAR', 'oracle' => 'VARCHAR2(4000)', default => 'VARCHAR(4000)' };
                $where[] = 'LOWER(CAST('.$quote($column).' AS '.$cast.")) LIKE ? ESCAPE '!'";
                $params[] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            }
        }
        $filter = $where ? '('.implode(' OR ', $where).')' : '1 = 1';
        if ('' !== (string) $company && !empty($source['company_column'])) { $filter .= ' AND '.$quote($source['company_column']).' = ?'; $params[] = $company; }
        $sql = 'SELECT DISTINCT '.implode(', ', array_map($quote, $selected)).' FROM '.$table.' WHERE '.$filter.' ORDER BY '.implode(', ', array_map($quote, $selected));
        $offset = (max(1, $page) - 1) * 25;
        $sql .= in_array($driver, ['postgres', 'postgresql', 'mysql', 'mariadb'], true) ? ' LIMIT 26 OFFSET '.$offset : ' OFFSET '.$offset.' ROWS FETCH NEXT 26 ROWS ONLY';
        $pdo = $this->schema->open($settings); $statement = $pdo->prepare($sql); $statement->execute($params); $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $items = [];
        foreach (array_slice($rows, 0, 25) as $row) {
            $code = (string) ($row[$source['value']] ?? '');
            if ('' === $code) continue;
            $items[] = ['value' => $code, 'label' => (string) ($row[$source['label']] ?? ''), 'barcode' => (string) ($row[$source['barcode'] ?? ''] ?? ''), 'company' => (string) ($row[$source['company_column'] ?? ''] ?? '')];
        }
        return ['items' => $items, 'more' => count($rows) > 25];
    }
}
