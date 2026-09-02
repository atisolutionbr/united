<?php

namespace App\Entity\AI;

use App\Repository\AI\AiModelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiModelRepository::class)]
#[ORM\Table(name: 'ai_models')]
#[ORM\Index(name: 'idx_ai_models_provider', columns: ['provider'])]
#[ORM\Index(name: 'idx_ai_models_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class AiModel
{
    public const PROVIDER_OLLAMA = 'local_ollama';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_AZURE_OPENAI = 'azure_openai';
    public const PROVIDER_OTHER = 'other';

    public const MODEL_LLAMA3 = 'llama3';
    public const MODEL_MISTRAL = 'mistral';
    public const MODEL_GEMMA = 'gemma';
    public const MODEL_GPT4O = 'gpt-4o';
    public const MODEL_GPT4O_MINI = 'gpt-4o-mini';
    public const MODEL_GPT41 = 'gpt-4.1';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 191, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 50)]
    private string $provider = self::PROVIDER_OLLAMA;

    #[ORM\Column(name: 'model_name', length: 100)]
    private string $modelName = '';

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $endpoint = null;

    // API key stored encrypted — never raw
    #[ORM\Column(name: 'api_key_encrypted', length: 1024, nullable: true)]
    private ?string $apiKeyEncrypted = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $temperature = null;

    #[ORM\Column(name: 'max_tokens', nullable: true)]
    private ?int $maxTokens = null;

    #[ORM\Column(name: 'context_window', nullable: true)]
    private ?int $contextWindow = null;

    #[ORM\Column(name: 'supports_embeddings')]
    private bool $supportsEmbeddings = false;

    #[ORM\Column(name: 'is_default')]
    private bool $isDefault = false;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): static { $this->provider = $provider; return $this; }
    public function getModelName(): string { return $this->modelName; }
    public function setModelName(string $modelName): static { $this->modelName = $modelName; return $this; }
    public function getEndpoint(): ?string { return $this->endpoint; }
    public function setEndpoint(?string $endpoint): static { $this->endpoint = $endpoint; return $this; }
    public function getApiKeyEncrypted(): ?string { return $this->apiKeyEncrypted; }
    public function setApiKeyEncrypted(?string $apiKeyEncrypted): static { $this->apiKeyEncrypted = $apiKeyEncrypted; return $this; }
    public function getTemperature(): ?string { return $this->temperature; }
    public function setTemperature(?string $temperature): static { $this->temperature = $temperature; return $this; }
    public function getMaxTokens(): ?int { return $this->maxTokens; }
    public function setMaxTokens(?int $maxTokens): static { $this->maxTokens = $maxTokens; return $this; }
    public function getContextWindow(): ?int { return $this->contextWindow; }
    public function setContextWindow(?int $contextWindow): static { $this->contextWindow = $contextWindow; return $this; }
    public function isSupportsEmbeddings(): bool { return $this->supportsEmbeddings; }
    public function setSupportsEmbeddings(bool $supportsEmbeddings): static { $this->supportsEmbeddings = $supportsEmbeddings; return $this; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $isDefault): static { $this->isDefault = $isDefault; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function isLocal(): bool { return $this->provider === self::PROVIDER_OLLAMA; }
    public function isExternal(): bool { return in_array($this->provider, [self::PROVIDER_OPENAI, self::PROVIDER_AZURE_OPENAI]); }

    public static function getProviders(): array
    {
        return [self::PROVIDER_OLLAMA, self::PROVIDER_OPENAI, self::PROVIDER_AZURE_OPENAI, self::PROVIDER_OTHER];
    }
}
