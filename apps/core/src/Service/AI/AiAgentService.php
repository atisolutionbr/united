<?php

namespace App\Service\AI;

use App\Entity\AI\AiAgent;
use App\Entity\AI\AiContext;
use App\Entity\AI\AiModel;
use App\Repository\AI\AiAgentRepository;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Runs AI agents with tool support.
 * Each agent has a type, model, context and a list of tools it can invoke.
 */
class AiAgentService
{
    public function __construct(
        private readonly AiProviderService $providerService,
        private readonly PromptTemplateService $promptTemplateService,
        private readonly AiToolRegistryService $toolRegistry,
        private readonly AiAgentRepository $agentRepository,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(
        AiAgent $agent,
        string $userMessage,
        ?string $userIdentifier = null,
    ): array {
        $model = $agent->getDefaultModel() ?? $this->providerService->resolveDefaultModel();
        if ($model === null) {
            return ['success' => false, 'response' => null, 'error' => 'Nenhum modelo configurado para o agente.'];
        }

        $context = $agent->getDefaultContext();

        // Build context data from agent tools
        $contextData = $this->gatherContextData($agent->getTools() ?? [], $userMessage);

        // Render system prompt
        $systemPrompt = '';
        $promptEntity = $agent->getPrompt();
        if ($promptEntity !== null) {
            $systemPrompt = $this->promptTemplateService->render($promptEntity, [
                'agent_name' => $agent->getName(),
                'context' => json_encode($contextData, JSON_UNESCAPED_UNICODE),
                'question' => $userMessage,
            ]);
        }

        $fullPrompt = empty($systemPrompt) ? $userMessage : $systemPrompt;

        return $this->providerService->dispatch(
            prompt: $fullPrompt,
            model: $model,
            context: $context,
            contextData: $contextData,
            userIdentifier: $userIdentifier,
            agentSlug: $agent->getSlug(),
        );
    }

    private function gatherContextData(array $tools, string $question): array
    {
        $contextData = [];
        foreach ($tools as $toolName) {
            try {
                $result = $this->toolRegistry->execute($toolName, ['question' => $question]);
                if ($result !== null) {
                    $contextData[$toolName] = $result;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Tool execution failed', ['tool' => $toolName, 'error' => $e->getMessage()]);
            }
        }
        return $contextData;
    }
}
