<?php

namespace App\Entity\Operations;

use App\Repository\Operations\PipelineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PipelineRepository::class)]
#[ORM\Table(name: 'pipelines')]
#[ORM\HasLifecycleCallbacks]
class Pipeline
{
    public const TYPE_INGESTION = 'ingestion';
    public const TYPE_TRANSFORMATION = 'transformation';
    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_EMBEDDINGS = 'embeddings';
    public const TYPE_AI_JOB = 'ai_job';
    public const TYPE_SYNC = 'sync';
    public const TYPE_BACKUP = 'backup';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_CRON = 'cron';
    public const TRIGGER_EVENT = 'event';
    public const TRIGGER_WEBHOOK = 'webhook';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ERROR = 'error';
    public const STATUS_NEVER_RUN = 'never_run';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 191, unique: true)]
    private string $slug;

    #[ORM\Column(name: 'kestra_namespace', length: 255, nullable: true)]
    private ?string $kestraNamespace = null;

    #[ORM\Column(name: 'kestra_flow_id', length: 255, nullable: true)]
    private ?string $kestraFlowId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_INGESTION;

    #[ORM\Column(name: 'trigger_type', length: 50)]
    private string $triggerType = self::TRIGGER_MANUAL;

    #[ORM\Column(name: 'cron_expression', length: 100, nullable: true)]
    private ?string $cronExpression = null;

    #[ORM\Column(name: 'kestra_yaml', type: Types::TEXT, nullable: true)]
    private ?string $kestraYaml = null;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'last_execution_id', length: 255, nullable: true)]
    private ?string $lastExecutionId = null;

    #[ORM\Column(name: 'last_execution_status', length: 50, nullable: true)]
    private ?string $lastExecutionStatus = null;

    #[ORM\Column(name: 'last_executed_at', nullable: true)]
    private ?\DateTimeImmutable $lastExecutedAt = null;

    #[ORM\Column(name: 'next_execution_at', nullable: true)]
    private ?\DateTimeImmutable $nextExecutionAt = null;

    #[ORM\Column(name: 'avg_duration_ms', nullable: true)]
    private ?int $avgDurationMs = null;

    #[ORM\Column(name: 'failure_count')]
    private int $failureCount = 0;

    #[ORM\Column(name: 'success_count')]
    private int $successCount = 0;

    #[ORM\Column(name: 'dataset_slug', length: 191, nullable: true)]
    private ?string $datasetSlug = null;

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
    public function getKestraNamespace(): ?string { return $this->kestraNamespace; }
    public function setKestraNamespace(?string $kestraNamespace): static { $this->kestraNamespace = $kestraNamespace; return $this; }
    public function getKestraFlowId(): ?string { return $this->kestraFlowId; }
    public function setKestraFlowId(?string $kestraFlowId): static { $this->kestraFlowId = $kestraFlowId; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getTriggerType(): string { return $this->triggerType; }
    public function setTriggerType(string $triggerType): static { $this->triggerType = $triggerType; return $this; }
    public function getCronExpression(): ?string { return $this->cronExpression; }
    public function setCronExpression(?string $cronExpression): static { $this->cronExpression = $cronExpression; return $this; }
    public function getKestraYaml(): ?string { return $this->kestraYaml; }
    public function setKestraYaml(?string $kestraYaml): static { $this->kestraYaml = $kestraYaml; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getLastExecutionId(): ?string { return $this->lastExecutionId; }
    public function setLastExecutionId(?string $v): static { $this->lastExecutionId = $v; return $this; }
    public function getLastExecutionStatus(): ?string { return $this->lastExecutionStatus; }
    public function setLastExecutionStatus(?string $v): static { $this->lastExecutionStatus = $v; return $this; }
    public function getLastExecutedAt(): ?\DateTimeImmutable { return $this->lastExecutedAt; }
    public function setLastExecutedAt(?\DateTimeImmutable $v): static { $this->lastExecutedAt = $v; return $this; }
    public function getNextExecutionAt(): ?\DateTimeImmutable { return $this->nextExecutionAt; }
    public function setNextExecutionAt(?\DateTimeImmutable $v): static { $this->nextExecutionAt = $v; return $this; }
    public function getAvgDurationMs(): ?int { return $this->avgDurationMs; }
    public function setAvgDurationMs(?int $v): static { $this->avgDurationMs = $v; return $this; }
    public function getFailureCount(): int { return $this->failureCount; }
    public function setFailureCount(int $v): static { $this->failureCount = $v; return $this; }
    public function getSuccessCount(): int { return $this->successCount; }
    public function setSuccessCount(int $v): static { $this->successCount = $v; return $this; }
    public function getDatasetSlug(): ?string { return $this->datasetSlug; }
    public function setDatasetSlug(?string $v): static { $this->datasetSlug = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function hasKestraFlow(): bool { return !empty($this->kestraNamespace) && !empty($this->kestraFlowId); }

    public const TYPES = [self::TYPE_INGESTION, self::TYPE_TRANSFORMATION, self::TYPE_WAREHOUSE, self::TYPE_EMBEDDINGS, self::TYPE_AI_JOB, self::TYPE_SYNC, self::TYPE_BACKUP];
    public const TRIGGER_TYPES = [self::TRIGGER_MANUAL, self::TRIGGER_CRON, self::TRIGGER_EVENT, self::TRIGGER_WEBHOOK];

    public static function getTypes(): array
    {
        return self::TYPES;
    }
}
