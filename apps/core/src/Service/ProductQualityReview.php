<?php

namespace App\Service;

final class ProductQualityReview
{
    public function __construct(private readonly DatabaseSchemaInspector $schema) {}

    public static function normalizeDescription(string $text, string $case = 'upper'): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        return 'lower' === $case ? mb_strtolower($text, 'UTF-8') : mb_strtoupper($text, 'UTF-8');
    }

    public static function fiscalIssues(array $row, array $mapping): array
    {
        $issues = [];
        $ncm = trim((string) ($row['ncm'] ?? ''));
        if (empty($mapping['ncm'])) $issues[] = 'NCM não vinculado';
        elseif (!preg_match('/^\d{8}$/D', $ncm)) $issues[] = 'NCM ausente ou fora do formato de oito dígitos';
        foreach (['cst_ibs_cbs' => ['CST IBS/CBS', 3], 'cclass_trib' => ['cClassTrib', 6]] as $key => [$label, $length]) {
            if (empty($mapping[$key])) $issues[] = $label.' não vinculado';
            elseif (!preg_match('/^\d{'.$length.'}$/D', trim((string) ($row[$key] ?? '')))) $issues[] = $label.' ausente ou com formato inválido';
        }
        if (preg_match('/^\d{3}$/D', (string) ($row['cst_ibs_cbs'] ?? '')) && preg_match('/^\d{6}$/D', (string) ($row['cclass_trib'] ?? '')) && !str_starts_with($row['cclass_trib'], $row['cst_ibs_cbs'])) $issues[] = 'CST e prefixo de cClassTrib divergentes: revisar tabela oficial';
        foreach (['ibs_rate' => 'IBS', 'cbs_rate' => 'CBS'] as $key => $label) {
            if (empty($mapping[$key])) $issues[] = 'Alíquota '.$label.' não vinculada';
            elseif (!is_numeric($row[$key] ?? null) || (float) $row[$key] < 0 || (float) $row[$key] > 100) $issues[] = 'Alíquota '.$label.' ausente ou fora da faixa de 0 a 100';
        }
        if (empty($mapping['tax_selective'])) $issues[] = 'Imposto Seletivo: enquadramento não vinculado';
        return $issues;
    }

    public function analyze(array $settings, array $binding, string $case = 'upper'): array
    {
        [$pdo, $quote, $table, $mapping] = $this->source($settings, $binding);
        $selects = [];
        foreach ($mapping as $key => $column) $selects[] = $quote($column).' AS '.$quote($key);
        $statement = $pdo->query('SELECT '.implode(', ', $selects).' FROM '.$table.' ORDER BY '.$quote($mapping['product_code']));
        $seen = []; $issues = []; $count = 0; $duplicates = 0; $caseCount = 0; $fiscalCount = 0;
        // Stream the source; keep at most 2,000 detailed findings in a single review.
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            ++$count;
            $description = (string) ($row['product_name'] ?? '');
            $suggestion = self::normalizeDescription($description, $case);
            $duplicateKey = self::normalizeDescription($description).'|'.self::normalizeDescription((string) ($row['unit'] ?? ''));
            $messages = self::fiscalIssues($row, $mapping);
            if ($messages) ++$fiscalCount;
            if ($description !== $suggestion) { ++$caseCount; $messages[] = 'Padronizar espaços e '.('lower' === $case ? 'minúsculas' : 'maiúsculas'); }
            if ('' !== trim($description) && isset($seen[$duplicateKey])) { ++$duplicates; $messages[] = 'Possível duplicidade de descrição/unidade com '.$seen[$duplicateKey].'; conferir antes de unificar'; }
            else $seen[$duplicateKey] = ($row['company'] ?? '').'/'.$row['product_code'];
            if ($messages && count($issues) < 2000) $issues[] = ['row' => $row, 'suggestion' => $suggestion, 'messages' => $messages, 'canNormalize' => $description !== $suggestion && isset($mapping['company'])];
        }
        return ['count' => $count, 'duplicates' => $duplicates, 'caseCount' => $caseCount, 'fiscalCount' => $fiscalCount, 'issues' => $issues, 'truncated' => count($issues) === 2000, 'mapping' => $mapping, 'table' => $binding['table']];
    }

    public function normalize(array $settings, array $binding, array $row, string $case): void
    {
        [$pdo, $quote, $table, $mapping] = $this->source($settings, $binding);
        if (!isset($mapping['company'], $row['company'], $row['product_code'], $row['product_name'])) throw new \InvalidArgumentException('Vincule empresa e código para identificar o produto com segurança.');
        $new = self::normalizeDescription($row['product_name'], $case);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('UPDATE '.$table.' SET '.$quote($mapping['product_name']).' = ? WHERE '.$quote($mapping['company']).' = ? AND '.$quote($mapping['product_code']).' = ? AND '.$quote($mapping['product_name']).' = ?');
            $statement->execute([$new, $row['company'], $row['product_code'], $row['product_name']]);
            if (1 !== $statement->rowCount()) throw new \RuntimeException('Produto alterado ou chave não única. Atualize a análise.');
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    private function source(array $settings, array $binding): array
    {
        $driver = $settings['driver'] ?? 'sqlserver';
        $quote = static function (string $name) use ($driver): string {
            ProcessGateway::identifier($name);
            return implode('.', array_map(static fn ($p) => match ($driver) { 'sqlserver' => '['.$p.']', 'mysql', 'mariadb' => '`'.$p.'`', default => '"'.$p.'"' }, explode('.', $name)));
        };
        $table = $quote($binding['table'] ?? '');
        $columns = $this->schema->columns($settings, $binding['table'])['columns'];
        $mapping = [];
        foreach ($binding['mapping'] ?? [] as $key => $column) if ($column && in_array($column, $columns, true)) $mapping[$key] = $column;
        if (!isset($mapping['product_name'], $mapping['product_code'])) throw new \InvalidArgumentException('Vincule nome e código do produto antes da análise.');
        return [$this->schema->open($settings), $quote, $table, $mapping];
    }
}
