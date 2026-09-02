<?php

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\AnalyticsHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalyticsHistoryRepository::class)]
#[ORM\Table(name: 'analytics_history')]
#[ORM\Index(name: 'idx_analytics_history_event_type', columns: ['event_type'])]
#[ORM\Index(name: 'idx_analytics_history_status', columns: ['status'])]
#[ORM\Index(name: 'idx_analytics_history_created_at', columns: ['created_at'])]
class AnalyticsHistory
{
    public const EVENT_WAREHOUSE_REFRESH = 'warehouse_refresh';
    public const EVENT_MODEL_UPDATE = 'model_update';
    public const EVENT_INDICATOR_GENERATION = 'indicator_generation';
    public const EVENT_ETL_FAILURE = 'etl_failure';
    public const EVENT_METABASE_SYNC = 'metabase_sync';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RUNNING = 'running';
    public const STATUS_WARNING = 'warning';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'event_type', length: 50)]
    private string $eventType;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detail = null;

    #[ORM\Column(length: 30)]
    private string $status;

    #[ORM\Column(name: 'duration_ms', nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(name: 'rows_affected', nullable: true)]
    private ?int $rowsAffected = null;

    #[ORM\Column(name: 'metadata', type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEventType(): string { return $this->eventType; }
    public function setEventType(string $eventType): static { $this->eventType = $eventType; return $this; }

    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $subject): static { $this->subject = $subject; return $this; }

    public function getDetail(): ?string { return $this->detail; }
    public function setDetail(?string $detail): static { $this->detail = $detail; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getDurationMs(): ?int { return $this->durationMs; }
    public function setDurationMs(?int $durationMs): static { $this->durationMs = $durationMs; return $this; }

    public function getRowsAffected(): ?int { return $this->rowsAffected; }
    public function setRowsAffected(?int $rowsAffected): static { $this->rowsAffected = $rowsAffected; return $this; }

    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
