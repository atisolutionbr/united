<?php

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\MetabaseDashboardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MetabaseDashboardRepository::class)]
#[ORM\Table(name: 'metabase_dashboards')]
#[ORM\Index(name: 'idx_metabase_dashboards_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class MetabaseDashboard
{
    public const TYPE_DASHBOARD = 'dashboard';
    public const TYPE_QUESTION = 'question';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SYNCING = 'syncing';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'metabase_id', nullable: true)]
    private ?int $metabaseId = null;

    #[ORM\Column(name: 'embed_url', length: 1024, nullable: true)]
    private ?string $embedUrl = null;

    #[ORM\Column(name: 'public_uuid', length: 255, nullable: true)]
    private ?string $publicUuid = null;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_DASHBOARD;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dataset = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'allow_embed')]
    private bool $allowEmbed = false;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'metabase_updated_at', nullable: true)]
    private ?\DateTimeImmutable $metabaseUpdatedAt = null;

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

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getMetabaseId(): ?int { return $this->metabaseId; }
    public function setMetabaseId(?int $metabaseId): static { $this->metabaseId = $metabaseId; return $this; }

    public function getEmbedUrl(): ?string { return $this->embedUrl; }
    public function setEmbedUrl(?string $embedUrl): static { $this->embedUrl = $embedUrl; return $this; }

    public function getPublicUuid(): ?string { return $this->publicUuid; }
    public function setPublicUuid(?string $publicUuid): static { $this->publicUuid = $publicUuid; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getDataset(): ?string { return $this->dataset; }
    public function setDataset(?string $dataset): static { $this->dataset = $dataset; return $this; }

    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $origin): static { $this->origin = $origin; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function isAllowEmbed(): bool { return $this->allowEmbed; }
    public function setAllowEmbed(bool $allowEmbed): static { $this->allowEmbed = $allowEmbed; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getMetabaseUpdatedAt(): ?\DateTimeImmutable { return $this->metabaseUpdatedAt; }
    public function setMetabaseUpdatedAt(?\DateTimeImmutable $metabaseUpdatedAt): static { $this->metabaseUpdatedAt = $metabaseUpdatedAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
