<?php

namespace App\Service\AI;

use App\Entity\AI\AiInteraction;
use App\Entity\AI\AiModel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for Ollama local AI provider.
 * Communicates with Ollama REST API running in Docker.
 */
class OllamaService
{
    private const DEFAULT_ENDPOINT = 'http://ollama:11434';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function chat(
        string $prompt,
        string $modelName = 'llama3',
        string $endpoint = self::DEFAULT_ENDPOINT,
        array $contextData = [],
    ): array {
        $startTime = microtime(true);

        $systemPrompt = 'Você é o Assistente de Inteligência Territorial da Plataforma360, especializado em dados públicos brasileiros, turismo, indicadores governamentais e gestão territorial. Responda de forma objetiva, usando sempre os dados disponíveis na plataforma.';

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if (!empty($contextData)) {
            $contextText = "Contexto disponível:\n" . json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $messages[] = ['role' => 'user', 'content' => $contextText];
            $messages[] = ['role' => 'assistant', 'content' => 'Entendido. Utilizarei esses dados para responder suas perguntas.'];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $response = $this->httpClient->request('POST', rtrim($endpoint, '/') . '/api/chat', [
                'json' => [
                    'model' => $modelName,
                    'messages' => $messages,
                    'stream' => false,
                ],
                'timeout' => 120,
            ]);

            $data = $response->toArray();
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $responseText = $data['message']['content'] ?? '';
            $tokensInput = $data['prompt_eval_count'] ?? null;
            $tokensOutput = $data['eval_count'] ?? null;

            return [
                'success' => true,
                'response' => $responseText,
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'duration_ms' => $durationMs,
                'model' => $modelName,
                'provider' => AiModel::PROVIDER_OLLAMA,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Ollama chat failed', ['model' => $modelName, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'response' => null,
                'error' => $e->getMessage(),
                'provider' => AiModel::PROVIDER_OLLAMA,
            ];
        }
    }

    public function listModels(string $endpoint = self::DEFAULT_ENDPOINT): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($endpoint, '/') . '/api/tags', ['timeout' => 10]);
            $data = $response->toArray();
            return array_map(fn($m) => $m['name'], $data['models'] ?? []);
        } catch (\Throwable $e) {
            $this->logger->warning('Ollama list models failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function isAvailable(string $endpoint = self::DEFAULT_ENDPOINT): bool
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($endpoint, '/') . '/api/tags', ['timeout' => 5]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function embed(string $text, string $model = 'nomic-embed-text', string $endpoint = self::DEFAULT_ENDPOINT): ?array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($endpoint, '/') . '/api/embed', [
                'json' => ['model' => $model, 'input' => $text],
                'timeout' => 30,
            ]);
            $data = $response->toArray();
            return $data['embeddings'][0] ?? null;
        } catch (\Throwable $e) {
            $this->logger->warning('Ollama embed failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
