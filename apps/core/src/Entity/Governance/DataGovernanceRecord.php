<?php

namespace App\Entity\Governance;

use App\Repository\Governance\DataGovernanceRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DataGovernanceRecordRepository::class)]
#[ORM\Table(name: 'data_governance_records')]
#[ORM\HasLifecycleCallbacks]
class DataGovernanceRecord
{
    public const CLASSIFICATION_PUBLIC = 'public';
    public const CLASSIFICATION_INTERNAL = 'internal';
    public const CLASSIFICATION_RESTRICTED = 'restricted';
    public const CLASSIFICATION_SENSITIVE = 'sensitive';

    public const SENSITIVITY_NONE = 'none';
    public const SENSITIVITY_LOW = 'low';
    public const SENSITIVITY_MEDIUM = 'medium';
    public const SENSITIVITY_HIGH = 'high';

    public const LGPD_BASIS_CONSENT = 'consent';
    public const LGPD_BASIS_LEGAL_OBLIGATION = 'legal_obligation';
    public const LGPD_BASIS_PUBLIC_POLICY = 'public_policy';
    public const LGPD_BASIS_RESEARCH = 'research';
    public const LGPD_BASIS_CONTRACT = 'contract';
    public const LGPD_BASIS_NOT_APPLICABLE = 'not_applicable';

    public const CLASSIFICATIONS = [self::CLASSIFICATION_PUBLIC, self::CLASSIFICATION_INTERNAL, self::CLASSIFICATION_RESTRICTED, self::CLASSIFICATION_SENSITIVE];
    public const SENSITIVITY_LEVELS = [self::SENSITIVITY_NONE, self::SENSITIVITY_LOW, self::SENSITIVITY_MEDIUM, self::SENSITIVITY_HIGH];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'dataset_id', nullable: true)]
    private ?int $datasetId = null;

    #[ORM\Column(name: 'dataset_name', length: 255)]
    private string $datasetName;

    #[ORM\Column(name: 'dataset_slug', length: 191, nullable: true, unique: true)]
    private ?string $datasetSlug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $owner = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $steward = null;

    #[ORM\Column(length: 30)]
    private string $classification = self::CLASSIFICATION_PUBLIC;

    #[ORM\Column(name: 'retention_days', nullable: true)]
    private ?int $retentionDays = null;

    #[ORM\Column(name: 'sensitivity_level', length: 20)]
    private string $sensitivityLevel = self::SENSITIVITY_NONE;

    #[ORM\Column(name: 'lgpd_applicable')]
    private bool $lgpdApplicable = false;

    #[ORM\Column(name: 'lgpd_basis', length: 30, nullable: true)]
    private ?string $lgpdBasis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(name: 'tenant_id', nullable: true)]
    private ?int $tenantId = null;

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
    public function getDatasetId(): ?int { return $this->datasetId; }
    public function setDatasetId(?int $v): static { $this->datasetId = $v; return $this; }
    public function getDatasetName(): string { return $this->datasetName; }
    public function setDatasetName(string $v): static { $this->datasetName = $v; return $this; }
    public function getDatasetSlug(): ?string { return $this->datasetSlug; }
    public function setDatasetSlug(?string $v): static { $this->datasetSlug = $v; return $this; }
    public function getOwner(): ?string { return $this->owner; }
    public function setOwner(?string $owner): static { $this->owner = $owner; return $this; }
    public function getSteward(): ?string { return $this->steward; }
    public function setSteward(?string $steward): static { $this->steward = $steward; return $this; }
    public function getClassification(): string { return $this->classification; }
    public function setClassification(string $classification): static { $this->classification = $classification; return $this; }
    public function getRetentionDays(): ?int { return $this->retentionDays; }
    public function setRetentionDays(?int $v): static { $this->retentionDays = $v; return $this; }
    public function getSensitivityLevel(): string { return $this->sensitivityLevel; }
    public function setSensitivityLevel(string $v): static { $this->sensitivityLevel = $v; return $this; }
    public function isLgpdApplicable(): bool { return $this->lgpdApplicable; }
    public function setLgpdApplicable(bool $v): static { $this->lgpdApplicable = $v; return $this; }
    public function getLgpdBasis(): ?string { return $this->lgpdBasis; }
    public function setLgpdBasis(?string $v): static { $this->lgpdBasis = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $tags): static { $this->tags = $tags; return $this; }
    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $v): static { $this->tenantId = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
