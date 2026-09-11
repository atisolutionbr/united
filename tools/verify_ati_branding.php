<?php

declare(strict_types=1);

/**
 * Prevents development-tool branding from reaching the United · Ati Solution deliverable.
 * Technical provider adapters are intentionally outside this product-brand scan.
 */

$root = (string) (getenv('BRAND_VERIFY_ROOT') ?: dirname(__DIR__));
$forbidden = [
    '/co'.'dex/i',
    '/lov'.'able/i',
    '/chat'.'gpt/i',
    '/github\s+co'.'pilot/i',
    '/\bcl'.'oud\b/i',
];
$ignored = ['.git', 'vendor', 'node_modules', 'var/cache', 'public/vendor', 'public/assets/bundles'];
$allowedTechnicalFiles = [
    'apps/core/src/Service/AI/OpenAiService.php',
    'apps/core/src/Service/AI/AiProviderService.php',
    'apps/core/src/Entity/AI/AiModel.php',
    'apps/core/src/Entity/Governance/CostRecord.php',
];
$violations = [];

$inspect = static function (string $origin, string $content) use (&$violations, $forbidden): void {
    foreach ($forbidden as $pattern) {
        if (preg_match($pattern, $content) === 1) {
            $violations[] = $origin;
            return;
        }
    }
};

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
    $absolute = $file->getPathname();
    $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
    if (in_array($relative, $allowedTechnicalFiles, true)) continue;
    $isIgnored = false;
    foreach ($ignored as $directory) {
        if (str_contains('/'.$relative.'/', '/'.$directory.'/')) {
            $isIgnored = true;
            break;
        }
    }
    if ($isIgnored) continue;
    if (str_contains('/'.$relative, '/public/assets/') && preg_match('#/(?:kflow-[^/]+\.js|styles/kflow-[^/]+\.css|manifest\.json)$#', $relative) !== 1) continue;
    if ($file->getSize() > 4_000_000) continue;
    $content = @file_get_contents($absolute);
    if (false !== $content && !str_contains($content, "\0")) $inspect($relative, $content);
}

if (in_array('--database', $argv, true)) {
    $url = (string) getenv('DATABASE_URL');
    $parts = parse_url($url);
    if (false === $parts || !isset($parts['host'], $parts['path'])) {
        $violations[] = 'database: DATABASE_URL indisponível';
    } else {
        try {
            $pdo = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $parts['host'], $parts['port'] ?? 5432, ltrim($parts['path'], '/')), urldecode($parts['user'] ?? ''), urldecode($parts['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $columns = $pdo->query("SELECT table_schema, table_name, column_name FROM information_schema.columns WHERE table_schema NOT IN ('pg_catalog', 'information_schema') AND data_type IN ('text', 'character varying', 'json', 'jsonb')")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                $quote = static fn (string $identifier): string => '"'.str_replace('"', '""', $identifier).'"';
                $query = sprintf('SELECT %s::text FROM %s.%s WHERE %s IS NOT NULL LIMIT 1000', $quote($column['column_name']), $quote($column['table_schema']), $quote($column['table_name']), $quote($column['column_name']));
                foreach ($pdo->query($query)->fetchAll(PDO::FETCH_COLUMN) as $value) $inspect(sprintf('database:%s.%s.%s', $column['table_schema'], $column['table_name'], $column['column_name']), (string) $value);
            }
        } catch (Throwable $exception) {
            $violations[] = 'database: verificação não executada';
        }
    }
}

foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--http=')) continue;
    $url = substr($argument, strlen('--http='));
    $response = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true]]));
    if (false === $response) $violations[] = 'http: resposta indisponível';
    else $inspect('http:'.$url, $response);
}

if ([] !== $violations) {
    fwrite(STDERR, "Falha na proteção de marca:\n- ".implode("\n- ", array_values(array_unique($violations)))."\n");
    exit(1);
}

fwrite(STDOUT, "Proteção de marca Ati Solution aprovada.\n");
