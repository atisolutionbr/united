<?php

namespace App\Service\AI;

use App\Entity\AI\AiInteraction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Governance service for AI interactions.
 * Records all prompts, responses, tokens, costs and status.
 */
class AiGovernanceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function startInteraction(
        string $provider,
        string $modelName,
        string $prompt,
        ?string $userIdentifier = null,
        ?string $agentSlug = null,
        bool $isExternal = false,
        array $contextData = [],
    ): AiInteraction {
        $interaction = new AiInteraction();
        $interaction->setProvider($provider);
        $interaction->setModelName($modelName);
        $interaction->setPrompt($prompt);
        $interaction->setUserIdentifier($userIdentifier);
        $interaction->setAgentSlug($agentSlug);
        $interaction->setIsExternalProvider($isExternal);
        $interaction->setStatus(AiInteraction::STATUS_RUNNING);

        if (!empty($contextData)) {
            $interaction->setContextUsed($contextData);
        }

        $this->em->persist($interaction);
        $this->em->flush();

        return $interaction;
    }

    public function completeInteraction(
        AiInteraction $interaction,
        ?string $response,
        ?int $tokensInput = null,
        ?int $tokensOutput = null,
        ?int $durationMs = null,
        ?string $costUsd = null,
        string $status = AiInteraction::STATUS_SUCCESS,
        ?string $errorMessage = null,
    ): void {
        $interaction->setResponse($response);
        $interaction->setStatus($status);
        $interaction->setTokensInput($tokensInput);
        $interaction->setTokensOutput($tokensOutput);
        $interaction->setDurationMs($durationMs);
        $interaction->setEstimatedCostUsd($costUsd);
        $interaction->setErrorMessage($errorMessage);

        $this->em->flush();

        if ($status === AiInteraction::STATUS_FAILED) {
            $this->logger->warning('AI interaction failed', [
                'provider' => $interaction->getProvider(),
                'model' => $interaction->getModelName(),
                'error' => $errorMessage,
            ]);
        }
    }

    public function getStats(): array
    {
        $conn = $this->em->getConnection();

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM ai_interactions');
        $success = (int) $conn->fetchOne("SELECT COUNT(*) FROM ai_interactions WHERE status = 'success'");
        $failed = (int) $conn->fetchOne("SELECT COUNT(*) FROM ai_interactions WHERE status = 'failed'");
        $external = (int) $conn->fetchOne('SELECT COUNT(*) FROM ai_interactions WHERE is_external_provider = true');
        $totalTokens = (int) $conn->fetchOne('SELECT COALESCE(SUM(tokens_input + tokens_output), 0) FROM ai_interactions');
        $avgDuration = (float) ($conn->fetchOne("SELECT COALESCE(AVG(duration_ms), 0) FROM ai_interactions WHERE status = 'success'") ?? 0);

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'external' => $external,
            'total_tokens' => $totalTokens,
            'avg_duration_ms' => round($avgDuration),
        ];
    }
}
