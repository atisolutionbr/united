<?php

namespace App\Service\AI;

use App\Entity\AI\AiContext;
use App\Entity\AI\AiInteraction;
use App\Entity\AI\AiModel;
use App\Repository\AI\AiModelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\ByteString;

/**
 * Dispatches AI requests to the correct provider (Ollama or OpenAI).
 * Handles interaction logging and governance.
 */
class AiProviderService
{
    public function __construct(
        private readonly OllamaService $ollamaService,
        private readonly OpenAiService $openAiService,
        private readonly AiGovernanceService $governanceService,
        private readonly AiModelRepository $modelRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Main dispatch — routes to correct provider, logs governance, returns response text.
     */
    public function dispatch(
        string $prompt,
        AiModel $model,
        ?AiContext $context = null,
        array $contextData = [],
        ?string $userIdentifier = null,
        ?string $agentSlug = null,
    ): array {
        // Security: check external policy
        if ($model->isExternal() && $context !== null && !$context->isAllowedForExternal()) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'O contexto selecionado não permite envio para provedores externos por política de privacidade.',
                'blocked' => true,
            ];
        }

        $interaction = $this->governanceService->startInteraction(
            provider: $model->getProvider(),
            modelName: $model->getModelName(),
            prompt: $prompt,
            userIdentifier: $userIdentifier,
            agentSlug: $agentSlug,
            isExternal: $model->isExternal(),
            contextData: $contextData,
        );

        try {
            if ($model->getProvider() === AiModel::PROVIDER_OLLAMA) {
                $result = $this->ollamaService->chat(
                    prompt: $prompt,
                    modelName: $model->getModelName(),
                    endpoint: $model->getEndpoint() ?? 'http://ollama:11434',
                    contextData: $contextData,
                );
            } elseif ($model->getProvider() === AiModel::PROVIDER_OPENAI) {
                $apiKey = $this->decryptApiKey($model->getApiKeyEncrypted());
                $result = $this->openAiService->chat(
                    prompt: $prompt,
                    apiKey: $apiKey,
                    modelName: $model->getModelName(),
                    contextData: $contextData,
                    temperature: (float) ($model->getTemperature() ?? 0.7),
                    maxTokens: $model->getMaxTokens() ?? 2048,
                );
            } else {
                $result = ['success' => false, 'error' => 'Provider não suportado: ' . $model->getProvider(), 'response' => null];
            }

            $this->governanceService->completeInteraction(
                interaction: $interaction,
                response: $result['response'],
                tokensInput: $result['tokens_input'] ?? null,
                tokensOutput: $result['tokens_output'] ?? null,
                durationMs: $result['duration_ms'] ?? null,
                costUsd: isset($result['cost_usd']) ? (string) $result['cost_usd'] : null,
                status: $result['success'] ? AiInteraction::STATUS_SUCCESS : AiInteraction::STATUS_FAILED,
                errorMessage: $result['error'] ?? null,
            );

            return $result;
        } catch (\Throwable $e) {
            $this->governanceService->completeInteraction(
                interaction: $interaction,
                response: null,
                status: AiInteraction::STATUS_FAILED,
                errorMessage: $e->getMessage(),
            );
            return ['success' => false, 'response' => null, 'error' => $e->getMessage()];
        }
    }

    public function resolveDefaultModel(): ?AiModel
    {
        return $this->modelRepository->findDefault() ?? $this->modelRepository->findActive()[0] ?? null;
    }

    private function decryptApiKey(?string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }
        // Simple base64 decoding (replace with proper encryption in production)
        $decoded = base64_decode($encrypted, true);
        return $decoded !== false ? $decoded : $encrypted;
    }
}
