<?php

namespace App\Entity\Operations;

use App\Repository\Operations\SystemMetricRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemMetricRepository::class)]
#[ORM\Table(name: 'system_metrics')]
#[ORM\Index(name: 'idx_sm_source_metric', columns: ['source', 'metric_name'])]
#[ORM\Index(name: 'idx_sm_recorded', columns: ['recorded_at'])]
class SystemMetric
{
    public const SOURCE_SYMFONY = 'symfony';
    public const SOURCE_KESTRA = 'kestra';
    public const SOURCE_POSTGRES = 'postgres';
    public const SOURCE_OLLAMA = 'ollama';
    public const SOURCE_QDRANT = 'qdrant';
    public const SOURCE_NGINX = 'nginx';
    public const SOURCE_METABASE = 'metabase';
    public const SOURCE_STORAGE = 'storage';

    public const METRIC_CPU = 'cpu_usage_pct';
    public const METRIC_MEMORY = 'memory_usage_pct';
    public const METRIC_DISK = 'disk_usage_pct';
    public const METRIC_RESPONSE_TIME = 'response_time_ms';
    public const METRIC_STORAGE_BYTES = 'storage_bytes';
    public const METRIC_QUEUE_SIZE = 'queue_size';
    public const METRIC_ERROR_RATE = 'error_rate_pct';
    public const METRIC_HEALTH = 'health_status';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'metric_name', length: 100)]
    private string $metricName;

    #[ORM\Column(name: 'metric_type', length: 50)]
    private string $metricType = 'gauge';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 4)]
    private string $value;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $labels = null;

    #[ORM\Column(length: 50)]
    private string $source = self::SOURCE_SYMFONY;

    #[ORM\Column(name: 'recorded_at')]
    private \DateTimeImmutable $recordedAt;

    public function __construct()
    {
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMetricName(): string { return $this->metricName; }
    public function setMetricName(string $metricName): static { $this->metricName = $metricName; return $this; }
    public function getMetricType(): string { return $this->metricType; }
    public function setMetricType(string $metricType): static { $this->metricType = $metricType; return $this; }
    public function getValue(): string { return $this->value; }
    public function setFloatValue(float $value): static { $this->value = (string) $value; return $this; }
    public function getFloatValue(): float { return (float) $this->value; }
    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(?string $unit): static { $this->unit = $unit; return $this; }
    public function getLabels(): ?array { return $this->labels; }
    public function setLabels(?array $labels): static { $this->labels = $labels; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
}
