<?php

namespace App\Service\AI;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Converts natural language questions to safe SQL SELECT queries.
 * SECURITY: Only SELECT is allowed. DDL/DML is blocked.
 */
class NaturalLanguageSqlService
{
    private const ALLOWED_SCHEMA_PREFIXES = ['warehouse.', 'public.staging_'];
    private const BLOCKED_KEYWORDS = ['DROP', 'DELETE', 'TRUNCATE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'pg_', 'information_schema'];

    public function __construct(
        private readonly Connection $connection,
        private readonly AiProviderService $providerService,
        private readonly PromptTemplateService $promptTemplateService,
        private readonly LoggerInterface $logger,
    ) {}

    public function convert(
        string $question,
        array $allowedTables,
        ?string $schemaContext = null,
    ): array {
        $tablesStr = implode(', ', $allowedTables);
        $schema = $schemaContext ?? $this->buildSchemaContext($allowedTables);

        $promptText = $this->promptTemplateService->renderByPurpose('nl_to_sql', [
            'tables' => $tablesStr,
            'schema' => $schema,
            'question' => $question,
        ]);

        if ($promptText === null) {
            $promptText = "Converta para SQL (somente SELECT, máx 100 linhas). Tabelas: {$tablesStr}. Pergunta: {$question}";
        }

        $model = $this->providerService->resolveDefaultModel();
        if ($model === null) {
            return ['success' => false, 'error' => 'Nenhum modelo de IA configurado.', 'sql' => null];
        }

        $result = $this->providerService->dispatch(
            prompt: $promptText,
            model: $model,
            agentSlug: 'nl-to-sql',
        );

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Falha ao gerar SQL.', 'sql' => null];
        }

        $sql = $this->extractSql($result['response'] ?? '');
        if ($sql === null) {
            return ['success' => false, 'error' => 'Não foi possível extrair SQL da resposta.', 'sql' => null, 'raw' => $result['response']];
        }

        $validation = $this->validateSql($sql, $allowedTables);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['reason'], 'sql' => $sql];
        }

        return ['success' => true, 'sql' => $sql];
    }

    public function executeSecure(string $sql, array $allowedTables, int $maxRows = 100): array
    {
        $validation = $this->validateSql($sql, $allowedTables);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['reason'], 'rows' => []];
        }

        // Enforce row limit
        if (!str_contains(strtoupper($sql), 'LIMIT')) {
            $sql = rtrim($sql, '; ') . " LIMIT {$maxRows}";
        }

        try {
            $rows = $this->connection->fetchAllAssociative($sql);
            return ['success' => true, 'rows' => $rows, 'count' => count($rows)];
        } catch (\Throwable $e) {
            $this->logger->error('NL-to-SQL execution failed', ['sql' => $sql, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Erro ao executar a consulta: ' . $e->getMessage(), 'rows' => []];
        }
    }

    private function validateSql(string $sql, array $allowedTables): array
    {
        $normalized = strtoupper(trim($sql));

        // Must start with SELECT
        if (!str_starts_with($normalized, 'SELECT')) {
            return ['valid' => false, 'reason' => 'Apenas consultas SELECT são permitidas.'];
        }

        // Block dangerous keywords
        foreach (self::BLOCKED_KEYWORDS as $kw) {
            if (str_contains(strtoupper($sql), strtoupper($kw))) {
                return ['valid' => false, 'reason' => "Palavra-chave proibida detectada: {$kw}"];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    private function extractSql(string $response): ?string
    {
        // Try to extract SQL from markdown code block
        if (preg_match('/```(?:sql)?\s*(SELECT.+?)```/si', $response, $m)) {
            return trim($m[1]);
        }
        // Try to find a bare SELECT statement
        if (preg_match('/\bSELECT\b.+/si', $response, $m)) {
            return trim($m[0]);
        }
        return null;
    }

    private function buildSchemaContext(array $tables): string
    {
        $lines = [];
        foreach ($tables as $table) {
            try {
                $cols = $this->connection->fetchAllAssociative(
                    "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema || '.' || table_name = :t ORDER BY ordinal_position LIMIT 20",
                    ['t' => $table]
                );
                if (!empty($cols)) {
                    $colDefs = implode(', ', array_map(fn($c) => $c['column_name'] . ' (' . $c['data_type'] . ')', $cols));
                    $lines[] = $table . ': ' . $colDefs;
                }
            } catch (\Throwable) {
                $lines[] = $table;
            }
        }
        return implode("\n", $lines);
    }
}
