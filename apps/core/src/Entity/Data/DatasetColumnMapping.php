<?php

namespace App\Entity\Data;

use App\Entity\ProviderPackage;
use App\Repository\Data\DatasetColumnMappingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DatasetColumnMappingRepository::class)]
#[ORM\Table(name: 'dataset_column_mappings')]
#[ORM\UniqueConstraint(name: 'uniq_column_mapping', columns: ['provider_package_id', 'original_column'])]
#[ORM\HasLifecycleCallbacks]
class DatasetColumnMapping
{
    public const TYPE_STRING = 'string';
    public const TYPE_TEXT = 'text';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_JSON = 'json';
    public const TYPE_GEOMETRY = 'geometry';

    public const RULE_TRIM = 'trim';
    public const RULE_UPPERCASE = 'uppercase';
    public const RULE_LOWERCASE = 'lowercase';
    public const RULE_NORMALIZE_UF = 'normalize_uf';
    public const RULE_NORMALIZE_CNPJ = 'normalize_cnpj';
    public const RULE_NORMALIZE_CPF = 'normalize_cpf';
    public const RULE_NORMALIZE_PHONE = 'normalize_phone';
    public const RULE_NORMALIZE_DATE = 'normalize_date';
    public const RULE_NORMALIZE_CITY = 'normalize_city';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'provider_package_id', nullable: false, onDelete: 'CASCADE')]
    private ProviderPackage $providerPackage;

    #[ORM\Column(name: 'original_column', length: 255)]
    #[Assert\NotBlank]
    private string $originalColumn;

    #[ORM\Column(name: 'normalized_column', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z][a-z0-9_]*$/', message: 'O campo normalizado deve usar snake_case.')]
    private string $normalizedColumn;

    #[ORM\Column(name: 'target_data_type', length: 30)]
    private string $targetDataType = self::TYPE_STRING;

    #[ORM\Column(name: 'normalization_rule', length: 50, nullable: true)]
    private ?string $normalizationRule = null;

    #[ORM\Column(name: 'required_field', options: ['default' => false])]
    private bool $requiredField = false;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
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
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_STRING,
            self::TYPE_TEXT,
            self::TYPE_INTEGER,
            self::TYPE_DECIMAL,
            self::TYPE_BOOLEAN,
            self::TYPE_DATE,
            self::TYPE_DATETIME,
            self::TYPE_JSON,
            self::TYPE_GEOMETRY,
        ];
    }

    public static function getAvailableRules(): array
    {
        return [
            self::RULE_TRIM,
            self::RULE_UPPERCASE,
            self::RULE_LOWERCASE,
            self::RULE_NORMALIZE_UF,
            self::RULE_NORMALIZE_CNPJ,
            self::RULE_NORMALIZE_CPF,
            self::RULE_NORMALIZE_PHONE,
            self::RULE_NORMALIZE_DATE,
            self::RULE_NORMALIZE_CITY,
        ];
    }

    public function getId(): ?int { return $this->id; }

    public function getProviderPackage(): ProviderPackage { return $this->providerPackage; }
    public function setProviderPackage(ProviderPackage $providerPackage): self { $this->providerPackage = $providerPackage; return $this; }

    public function getOriginalColumn(): string { return $this->originalColumn; }
    public function setOriginalColumn(string $originalColumn): self { $this->originalColumn = $originalColumn; return $this; }

    public function getNormalizedColumn(): string { return $this->normalizedColumn; }
    public function setNormalizedColumn(string $normalizedColumn): self { $this->normalizedColumn = $normalizedColumn; return $this; }

    public function getTargetDataType(): string { return $this->targetDataType; }
    public function setTargetDataType(string $targetDataType): self { $this->targetDataType = $targetDataType; return $this; }

    public function getNormalizationRule(): ?string { return $this->normalizationRule; }
    public function setNormalizationRule(?string $normalizationRule): self { $this->normalizationRule = $normalizationRule; return $this; }

    public function isRequiredField(): bool { return $this->requiredField; }
    public function setRequiredField(bool $requiredField): self { $this->requiredField = $requiredField; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
