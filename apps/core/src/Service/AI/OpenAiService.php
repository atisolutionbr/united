<?php

namespace App\Service\AI;

use App\Entity\AI\AiModel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for OpenAI external AI provider.
 * SECURITY: API key must be stored encrypted, decrypted only at runtime.
 * External usage is gated by AiContext::allowedForExternal flag.
 */
class OpenAiService
{
    private const API_BASE = 'https://api.openai.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function chat(
        string $prompt,
        string $apiKey,
        string $modelName = 'gpt-4o-mini',
        array $contextData = [],
        float $temperature = 0.7,
        int $maxTokens = 2048,
    ): array {
        $startTime = microtime(true);

        $systemPrompt = 'Você é o Assistente de Inteligência Territorial da Plataforma360, especializado em dados públicos brasileiros, turismo, indicadores governamentais e gestão territorial. Responda de forma objetiva e estruturada, referenciando os dados disponíveis.';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        if (!empty($contextData)) {
            $contextText = "Contexto de dados disponível:\n" . json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $messages[] = ['role' => 'user', 'content' => $contextText];
            $messages[] = ['role' => 'assistant', 'content' => 'Dados recebidos. Estou pronto para responder com base nessas informações.'];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $response = $this->httpClient->request('POST', self::API_BASE . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $modelName,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ],
                'timeout' => 60,
            ]);

            $data = $response->toArray();
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $content = $data['choices'][0]['message']['content'] ?? '';
            $usage = $data['usage'] ?? [];

            return [
                'success' => true,
                'response' => $content,
                'tokens_input' => $usage['prompt_tokens'] ?? null,
                'tokens_output' => $usage['completion_tokens'] ?? null,
                'duration_ms' => $durationMs,
                'model' => $modelName,
                'provider' => AiModel::PROVIDER_OPENAI,
                'cost_usd' => $this->estimateCost($modelName, $usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI chat failed', ['model' => $modelName, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'response' => null,
                'error' => $e->getMessage(),
                'provider' => AiModel::PROVIDER_OPENAI,
            ];
        }
    }

    public function embed(string $text, string $apiKey, string $model = 'text-embedding-3-small'): ?array
    {
        try {
            $response = $this->httpClient->request('POST', self::API_BASE . '/embeddings', [
                'headers' => ['Authorization' => 'Bearer ' . $apiKey],
                'json' => ['model' => $model, 'input' => $text],
                'timeout' => 30,
            ]);
            $data = $response->toArray();
            return $data['data'][0]['embedding'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->warning('OpenAI embed failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        // Approximate pricing per 1M tokens (USD) — update as needed
        $prices = [
            'gpt-4o' => ['input' => 5.0, 'output' => 15.0],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        ];
        $p = $prices[$model] ?? ['input' => 1.0, 'output' => 2.0];
        return round(($inputTokens * $p['input'] + $outputTokens * $p['output']) / 1_000_000, 6);
    }
}
