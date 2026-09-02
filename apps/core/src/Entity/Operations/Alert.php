<?php

namespace App\Entity\Operations;

use App\Repository\Operations\AlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alerts')]
#[ORM\Index(name: 'idx_alerts_level', columns: ['level'])]
#[ORM\Index(name: 'idx_alerts_status', columns: ['status'])]
class Alert
{
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_CRITICAL = 'critical';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    public const TYPE_PIPELINE_FAILED = 'pipeline_failed';
    public const TYPE_DATASET_STALE = 'dataset_stale';
    public const TYPE_STORAGE_FULL = 'storage_full';
    public const TYPE_AI_UNAVAILABLE = 'ai_unavailable';
    public const TYPE_WAREHOUSE_SLOW = 'warehouse_slow';
    public const TYPE_API_UNAVAILABLE = 'api_unavailable';
    public const TYPE_EMBEDDING_FAILED = 'embedding_failed';
    public const TYPE_METABASE_OFFLINE = 'metabase_offline';
    public const TYPE_KESTRA_OFFLINE = 'kestra_offline';
    public const TYPE_HIGH_ERROR_RATE = 'high_error_rate';
    public const TYPE_COST_THRESHOLD = 'cost_threshold';
    public const TYPE_GENERAL = 'general';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $type = self::TYPE_GENERAL;

    #[ORM\Column(length: 20)]
    private string $level = self::LEVEL_INFO;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(name: 'source_id', length: 255, nullable: true)]
    private ?string $sourceId = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'acknowledged_by', length: 255, nullable: true)]
    private ?string $acknowledgedBy = null;

    #[ORM\Column(name: 'acknowledged_at', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(name: 'resolved_at', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getLevel(): string { return $this->level; }
    public function setLevel(string $level): static { $this->level = $level; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $source): static { $this->source = $source; return $this; }
    public function getSourceId(): ?string { return $this->sourceId; }
    public function setSourceId(?string $v): static { $this->sourceId = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getAcknowledgedBy(): ?string { return $this->acknowledgedBy; }
    public function setAcknowledgedBy(?string $v): static { $this->acknowledgedBy = $v; return $this; }
    public function getAcknowledgedAt(): ?\DateTimeImmutable { return $this->acknowledgedAt; }
    public function setAcknowledgedAt(?\DateTimeImmutable $v): static { $this->acknowledgedAt = $v; return $this; }
    public function getResolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTimeImmutable $v): static { $this->resolvedAt = $v; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
}
