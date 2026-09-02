<?php

namespace App\Entity\AI;

use App\Repository\AI\AiContextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiContextRepository::class)]
#[ORM\Table(name: 'ai_contexts')]
#[ORM\HasLifecycleCallbacks]
class AiContext
{
    public const SOURCE_WAREHOUSE = 'warehouse';
    public const SOURCE_CATALOG = 'catalog';
    public const SOURCE_INDICATORS = 'indicators';
    public const SOURCE_ANALYTICS_API = 'analytics_api';
    public const SOURCE_DOCUMENTS = 'documents';
    public const SOURCE_LINEAGE = 'lineage';
    public const SOURCE_QUALITY = 'quality';

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

    #[ORM\Column(name: 'sources', type: Types::JSON)]
    private array $sources = [];

    #[ORM\Column(name: 'warehouse_tables', type: Types::JSON)]
    private array $warehouseTables = [];

    #[ORM\Column(name: 'allowed_for_external')]
    private bool $allowedForExternal = false;

    #[ORM\Column(name: 'max_rows_context', nullable: true, options: ['default' => 100])]
    private ?int $maxRowsContext = 100;

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
    public function getSources(): array { return $this->sources; }
    public function setSources(array $sources): static { $this->sources = $sources; return $this; }
    public function getWarehouseTables(): array { return $this->warehouseTables; }
    public function setWarehouseTables(array $warehouseTables): static { $this->warehouseTables = $warehouseTables; return $this; }
    public function isAllowedForExternal(): bool { return $this->allowedForExternal; }
    public function setAllowedForExternal(bool $allowedForExternal): static { $this->allowedForExternal = $allowedForExternal; return $this; }
    public function getMaxRowsContext(): ?int { return $this->maxRowsContext; }
    public function setMaxRowsContext(?int $maxRowsContext): static { $this->maxRowsContext = $maxRowsContext; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
