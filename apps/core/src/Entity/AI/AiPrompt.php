<?php

namespace App\Entity\AI;

use App\Repository\AI\AiPromptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiPromptRepository::class)]
#[ORM\Table(name: 'ai_prompts')]
#[ORM\Index(name: 'idx_ai_prompts_purpose', columns: ['purpose'])]
#[ORM\HasLifecycleCallbacks]
class AiPrompt
{
    public const PURPOSE_INDICATOR_ANALYSIS = 'indicator_analysis';
    public const PURPOSE_REPORT_GENERATION = 'report_generation';
    public const PURPOSE_DATASET_EXPLANATION = 'dataset_explanation';
    public const PURPOSE_EXECUTIVE_SUMMARY = 'executive_summary';
    public const PURPOSE_TERRITORIAL_COMPARISON = 'territorial_comparison';
    public const PURPOSE_DATA_QUALITY_DIAGNOSIS = 'data_quality_diagnosis';
    public const PURPOSE_GENERAL_ASSISTANT = 'general_assistant';
    public const PURPOSE_NL_TO_SQL = 'nl_to_sql';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 191, unique: true)]
    private string $slug;

    #[ORM\Column(length: 80)]
    private string $purpose = self::PURPOSE_GENERAL_ASSISTANT;

    #[ORM\Column(name: 'prompt_template', type: Types::TEXT)]
    private string $promptTemplate;

    #[ORM\Column(name: 'context_type', length: 100, nullable: true)]
    private ?string $contextType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private int $version = 1;

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
    public function getPurpose(): string { return $this->purpose; }
    public function setPurpose(string $purpose): static { $this->purpose = $purpose; return $this; }
    public function getPromptTemplate(): string { return $this->promptTemplate; }
    public function setPromptTemplate(string $promptTemplate): static { $this->promptTemplate = $promptTemplate; return $this; }
    public function getContextType(): ?string { return $this->contextType; }
    public function setContextType(?string $contextType): static { $this->contextType = $contextType; return $this; }
    public function getProvider(): ?string { return $this->provider; }
    public function setProvider(?string $provider): static { $this->provider = $provider; return $this; }
    public function getVersion(): int { return $this->version; }
    public function setVersion(int $version): static { $this->version = $version; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public static function getPurposes(): array
    {
        return [
            self::PURPOSE_INDICATOR_ANALYSIS,
            self::PURPOSE_REPORT_GENERATION,
            self::PURPOSE_DATASET_EXPLANATION,
            self::PURPOSE_EXECUTIVE_SUMMARY,
            self::PURPOSE_TERRITORIAL_COMPARISON,
            self::PURPOSE_DATA_QUALITY_DIAGNOSIS,
            self::PURPOSE_GENERAL_ASSISTANT,
            self::PURPOSE_NL_TO_SQL,
        ];
    }
}
