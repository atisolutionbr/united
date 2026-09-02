<?php

namespace App\Entity\AI;

use App\Repository\AI\AiInteractionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiInteractionRepository::class)]
#[ORM\Table(name: 'ai_interactions')]
#[ORM\Index(name: 'idx_ai_interactions_provider', columns: ['provider'])]
#[ORM\Index(name: 'idx_ai_interactions_status', columns: ['status'])]
#[ORM\Index(name: 'idx_ai_interactions_created_at', columns: ['created_at'])]
class AiInteraction
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RUNNING = 'running';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_identifier', length: 255, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(length: 50)]
    private string $provider;

    #[ORM\Column(name: 'model_name', length: 100)]
    private string $modelName;

    #[ORM\Column(name: 'agent_slug', length: 191, nullable: true)]
    private ?string $agentSlug = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $prompt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $response = null;

    #[ORM\Column(name: 'context_used', type: Types::JSON, nullable: true)]
    private ?array $contextUsed = null;

    #[ORM\Column(name: 'tokens_input', nullable: true)]
    private ?int $tokensInput = null;

    #[ORM\Column(name: 'tokens_output', nullable: true)]
    private ?int $tokensOutput = null;

    #[ORM\Column(name: 'estimated_cost_usd', type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    private ?string $estimatedCostUsd = null;

    #[ORM\Column(name: 'duration_ms', nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_RUNNING;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'is_external_provider')]
    private bool $isExternalProvider = false;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUserIdentifier(): ?string { return $this->userIdentifier; }
    public function setUserIdentifier(?string $userIdentifier): static { $this->userIdentifier = $userIdentifier; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): static { $this->provider = $provider; return $this; }
    public function getModelName(): string { return $this->modelName; }
    public function setModelName(string $modelName): static { $this->modelName = $modelName; return $this; }
    public function getAgentSlug(): ?string { return $this->agentSlug; }
    public function setAgentSlug(?string $agentSlug): static { $this->agentSlug = $agentSlug; return $this; }
    public function getPrompt(): string { return $this->prompt; }
    public function setPrompt(string $prompt): static { $this->prompt = $prompt; return $this; }
    public function getResponse(): ?string { return $this->response; }
    public function setResponse(?string $response): static { $this->response = $response; return $this; }
    public function getContextUsed(): ?array { return $this->contextUsed; }
    public function setContextUsed(?array $contextUsed): static { $this->contextUsed = $contextUsed; return $this; }
    public function getTokensInput(): ?int { return $this->tokensInput; }
    public function setTokensInput(?int $tokensInput): static { $this->tokensInput = $tokensInput; return $this; }
    public function getTokensOutput(): ?int { return $this->tokensOutput; }
    public function setTokensOutput(?int $tokensOutput): static { $this->tokensOutput = $tokensOutput; return $this; }
    public function getEstimatedCostUsd(): ?string { return $this->estimatedCostUsd; }
    public function setEstimatedCostUsd(?string $estimatedCostUsd): static { $this->estimatedCostUsd = $estimatedCostUsd; return $this; }
    public function getDurationMs(): ?int { return $this->durationMs; }
    public function setDurationMs(?int $durationMs): static { $this->durationMs = $durationMs; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): static { $this->errorMessage = $errorMessage; return $this; }
    public function isExternalProvider(): bool { return $this->isExternalProvider; }
    public function setIsExternalProvider(bool $isExternalProvider): static { $this->isExternalProvider = $isExternalProvider; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
