<?php

namespace App\Entity\AI;

use App\Repository\AI\AiAgentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiAgentRepository::class)]
#[ORM\Table(name: 'ai_agents')]
#[ORM\HasLifecycleCallbacks]
class AiAgent
{
    public const TYPE_TOURISM = 'turismo';
    public const TYPE_PUBLIC_DATA = 'dados_publicos';
    public const TYPE_EXECUTIVE = 'executivo';
    public const TYPE_TECHNICAL = 'tecnico';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 191, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'agent_type', length: 50)]
    private string $agentType = self::TYPE_PUBLIC_DATA;

    #[ORM\ManyToOne(targetEntity: AiModel::class)]
    #[ORM\JoinColumn(name: 'default_model_id', nullable: true, onDelete: 'SET NULL')]
    private ?AiModel $defaultModel = null;

    #[ORM\ManyToOne(targetEntity: AiContext::class)]
    #[ORM\JoinColumn(name: 'default_context_id', nullable: true, onDelete: 'SET NULL')]
    private ?AiContext $defaultContext = null;

    #[ORM\ManyToOne(targetEntity: AiPrompt::class)]
    #[ORM\JoinColumn(name: 'prompt_id', nullable: true, onDelete: 'SET NULL')]
    private ?AiPrompt $prompt = null;

    #[ORM\Column(type: Types::JSON)]
    private array $tools = [];

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

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
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getAgentType(): string { return $this->agentType; }
    public function setAgentType(string $agentType): static { $this->agentType = $agentType; return $this; }
    public function getDefaultModel(): ?AiModel { return $this->defaultModel; }
    public function setDefaultModel(?AiModel $defaultModel): static { $this->defaultModel = $defaultModel; return $this; }
    public function getDefaultContext(): ?AiContext { return $this->defaultContext; }
    public function setDefaultContext(?AiContext $defaultContext): static { $this->defaultContext = $defaultContext; return $this; }
    public function getPrompt(): ?AiPrompt { return $this->prompt; }
    public function setPrompt(?AiPrompt $prompt): static { $this->prompt = $prompt; return $this; }
    public function getTools(): array { return $this->tools; }
    public function setTools(array $tools): static { $this->tools = $tools; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public static function getAgentTypes(): array
    {
        return [self::TYPE_TOURISM, self::TYPE_PUBLIC_DATA, self::TYPE_EXECUTIVE, self::TYPE_TECHNICAL];
    }
}
