<?php

namespace App\Service\Observability;

use App\Service\Kestra\KestraService;
use App\Service\AI\OllamaService;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks health of all platform services and returns a unified status map.
 */
class HealthCheckService
{
    private const STATUS_ONLINE = 'online';
    private const STATUS_DEGRADED = 'degraded';
    private const STATUS_OFFLINE = 'offline';

    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly KestraService $kestraService,
        private readonly OllamaService $ollamaService,
        private readonly LoggerInterface $logger,
    ) {}

    public function checkAll(): array
    {
        return [
            'symfony' => $this->checkSymfony(),
            'postgres' => $this->checkPostgres(),
            'kestra' => $this->checkKestra(),
            'ollama' => $this->checkOllama(),
            'qdrant' => $this->checkQdrant(),
            'metabase' => $this->checkMetabase(),
            'storage' => $this->checkStorage(),
        ];
    }

    public function checkSymfony(): array
    {
        // If we're responding, Symfony is online
        $memory = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        return [
            'status' => self::STATUS_ONLINE,
            'memory_mb' => round($memory / 1024 / 1024, 1),
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 1),
            'checked_at' => date('H:i:s'),
        ];
    }

    public function checkPostgres(): array
    {
        try {
            $start = microtime(true);
            $version = $this->connection->fetchOne('SELECT version()');
            $latency = round((microtime(true) - $start) * 1000, 1);

            // Count tables in app schema
            $tables = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'"
            );

            return [
                'status' => $latency < 500 ? self::STATUS_ONLINE : self::STATUS_DEGRADED,
                'latency_ms' => $latency,
                'tables' => $tables,
                'version' => substr((string)$version, 0, 60),
            ];
        } catch (\Throwable $e) {
            return ['status' => self::STATUS_OFFLINE, 'error' => $e->getMessage()];
        }
    }

    public function checkKestra(): array
    {
        try {
            $start = microtime(true);
            $available = $this->kestraService->isAvailable();
            $latency = round((microtime(true) - $start) * 1000, 1);
            if (!$available) {
                return ['status' => self::STATUS_OFFLINE, 'latency_ms' => $latency];
            }
            return ['status' => self::STATUS_ONLINE, 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            return ['status' => self::STATUS_OFFLINE, 'error' => $e->getMessage()];
        }
    }

    public function checkOllama(): array
    {
        try {
            $start = microtime(true);
            $available = $this->ollamaService->isAvailable();
            $latency = round((microtime(true) - $start) * 1000, 1);
            if (!$available) {
                return ['status' => self::STATUS_OFFLINE];
            }
            $models = $this->ollamaService->listModels();
            return ['status' => self::STATUS_ONLINE, 'latency_ms' => $latency, 'models' => count($models)];
        } catch (\Throwable $e) {
            return ['status' => self::STATUS_OFFLINE, 'error' => $e->getMessage()];
        }
    }

    public function checkQdrant(): array
    {
        try {
            $start = microtime(true);
            $response = $this->httpClient->request('GET', 'http://qdrant:6333/health', ['timeout' => 5]);
            $latency = round((microtime(true) - $start) * 1000, 1);
            $data = $response->toArray();
            return [
                'status' => ($data['status'] ?? '') === 'ok' ? self::STATUS_ONLINE : self::STATUS_DEGRADED,
                'latency_ms' => $latency,
            ];
        } catch (\Throwable) {
            return ['status' => self::STATUS_OFFLINE];
        }
    }

    public function checkMetabase(): array
    {
        try {
            $start = microtime(true);
            // Try to reach Metabase health endpoint (configurable; default localhost:3000)
            $response = $this->httpClient->request('GET', 'http://metabase:3000/api/health', ['timeout' => 5]);
            $latency = round((microtime(true) - $start) * 1000, 1);
            $data = $response->toArray();
            return [
                'status' => ($data['status'] ?? '') === 'ok' ? self::STATUS_ONLINE : self::STATUS_DEGRADED,
                'latency_ms' => $latency,
            ];
        } catch (\Throwable) {
            return ['status' => self::STATUS_OFFLINE];
        }
    }

    public function checkStorage(): array
    {
        $paths = [
            'raw' => '/var/storage/raw',
            'staging' => '/var/storage/staging',
        ];
        $result = [];
        foreach ($paths as $name => $path) {
            if (is_dir($path)) {
                $result[$name] = [
                    'status' => self::STATUS_ONLINE,
                    'writable' => is_writable($path),
                ];
            } else {
                $result[$name] = ['status' => self::STATUS_OFFLINE];
            }
        }
        return ['status' => self::STATUS_ONLINE, 'paths' => $result];
    }

    public function getOverallStatus(array $health): string
    {
        $statuses = array_column($health, 'status');
        if (in_array(self::STATUS_OFFLINE, $statuses)) {
            return self::STATUS_DEGRADED;
        }
        if (in_array(self::STATUS_DEGRADED, $statuses)) {
            return self::STATUS_DEGRADED;
        }
        return self::STATUS_ONLINE;
    }
}
