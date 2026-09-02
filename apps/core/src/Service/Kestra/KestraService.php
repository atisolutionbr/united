<?php

namespace App\Service\Kestra;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Kestra REST API client.
 * Kestra runs at http://kestra:8080 when using Docker profile 'ops'.
 */
class KestraService
{
    private const DEFAULT_ENDPOINT = 'http://kestra:8080';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $kestraEndpoint = self::DEFAULT_ENDPOINT,
    ) {}

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->kestraEndpoint . '/api/v1/health', ['timeout' => 5]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getHealth(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->kestraEndpoint . '/api/v1/health', ['timeout' => 5]);
            return $response->toArray();
        } catch (\Throwable $e) {
            return ['status' => 'DOWN', 'error' => $e->getMessage()];
        }
    }

    /**
     * Trigger a Kestra flow execution.
     */
    public function triggerExecution(string $namespace, string $flowId, array $inputs = []): array
    {
        try {
            $url = "{$this->kestraEndpoint}/api/v1/executions/{$namespace}/{$flowId}";
            $response = $this->httpClient->request('POST', $url, [
                'json' => $inputs ?: new \stdClass(),
                'timeout' => 30,
            ]);
            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Kestra trigger failed', ['namespace' => $namespace, 'flow' => $flowId, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get a specific execution by ID.
     */
    public function getExecution(string $executionId): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->kestraEndpoint}/api/v1/executions/{$executionId}", ['timeout' => 15]);
            return $response->toArray();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * List recent executions, optionally filtered by namespace/flow.
     */
    public function listExecutions(?string $namespace = null, ?string $flowId = null, int $size = 50): array
    {
        try {
            $params = ['size' => $size, 'sort' => 'startDate,DESC'];
            if ($namespace) { $params['namespace'] = $namespace; }
            if ($flowId) { $params['flowId'] = $flowId; }

            $response = $this->httpClient->request('GET', "{$this->kestraEndpoint}/api/v1/executions/search", [
                'query' => $params,
                'timeout' => 15,
            ]);
            $data = $response->toArray();
            return $data['results'] ?? [];
        } catch (\Throwable $e) {
            $this->logger->warning('Kestra list executions failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * List flows in a namespace.
     */
    public function listFlows(?string $namespace = null): array
    {
        try {
            $url = "{$this->kestraEndpoint}/api/v1/flows/search";
            $params = ['size' => 100];
            if ($namespace) { $params['namespace'] = $namespace; }
            $response = $this->httpClient->request('GET', $url, ['query' => $params, 'timeout' => 15]);
            $data = $response->toArray();
            return $data['results'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get a flow definition including its YAML.
     */
    public function getFlow(string $namespace, string $flowId): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->kestraEndpoint}/api/v1/flows/{$namespace}/{$flowId}", ['timeout' => 10]);
            return $response->toArray();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Pause a scheduled flow.
     */
    public function pauseFlow(string $namespace, string $flowId): bool
    {
        try {
            $this->httpClient->request('POST', "{$this->kestraEndpoint}/api/v1/flows/{$namespace}/{$flowId}/disable", ['timeout' => 10]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resume a paused flow.
     */
    public function resumeFlow(string $namespace, string $flowId): bool
    {
        try {
            $this->httpClient->request('POST', "{$this->kestraEndpoint}/api/v1/flows/{$namespace}/{$flowId}/enable", ['timeout' => 10]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get execution logs.
     */
    public function getExecutionLogs(string $executionId): string
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->kestraEndpoint}/api/v1/logs/{$executionId}", ['timeout' => 20]);
            $data = $response->toArray();
            if (is_array($data)) {
                return implode("\n", array_map(fn($l) => "[{$l['level']}] {$l['message']}", $data));
            }
            return '';
        } catch (\Throwable) {
            return '';
        }
    }

    public function getEndpoint(): string
    {
        return $this->kestraEndpoint;
    }
}
