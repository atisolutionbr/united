<?php

namespace App\Entity\Operations;

use App\Repository\Operations\PipelineExecutionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PipelineExecutionRepository::class)]
#[ORM\Table(name: 'pipeline_executions')]
#[ORM\Index(name: 'idx_pe_status', columns: ['status'])]
#[ORM\Index(name: 'idx_pe_created', columns: ['created_at'])]
class PipelineExecution
{
    public const STATUS_CREATED = 'CREATED';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_WARNING = 'WARNING';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_SCHEDULED = 'scheduled';
    public const TRIGGER_API = 'api';
    public const TRIGGER_EVENT = 'event';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pipeline::class)]
    #[ORM\JoinColumn(name: 'pipeline_id', nullable: true, onDelete: 'SET NULL')]
    private ?Pipeline $pipeline = null;

    #[ORM\Column(name: 'kestra_execution_id', length: 255, nullable: true)]
    private ?string $kestraExecutionId = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_CREATED;

    #[ORM\Column(name: 'started_at', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(name: 'duration_ms', nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(name: 'triggered_by', length: 255, nullable: true)]
    private ?string $triggeredBy = null;

    #[ORM\Column(name: 'trigger_type', length: 50)]
    private string $triggerType = self::TRIGGER_MANUAL;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $logs = null;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'retry_count')]
    private int $retryCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $inputs = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $outputs = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPipeline(): ?Pipeline { return $this->pipeline; }
    public function setPipeline(?Pipeline $pipeline): static { $this->pipeline = $pipeline; return $this; }
    public function getKestraExecutionId(): ?string { return $this->kestraExecutionId; }
    public function setKestraExecutionId(?string $v): static { $this->kestraExecutionId = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $v): static { $this->startedAt = $v; return $this; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function setFinishedAt(?\DateTimeImmutable $v): static { $this->finishedAt = $v; return $this; }
    public function getDurationMs(): ?int { return $this->durationMs; }
    public function setDurationMs(?int $v): static { $this->durationMs = $v; return $this; }
    public function getTriggeredBy(): ?string { return $this->triggeredBy; }
    public function setTriggeredBy(?string $v): static { $this->triggeredBy = $v; return $this; }
    public function getTriggerType(): string { return $this->triggerType; }
    public function setTriggerType(string $v): static { $this->triggerType = $v; return $this; }
    public function getLogs(): ?string { return $this->logs; }
    public function setLogs(?string $logs): static { $this->logs = $logs; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }
    public function getRetryCount(): int { return $this->retryCount; }
    public function setRetryCount(int $v): static { $this->retryCount = $v; return $this; }
    public function getInputs(): ?array { return $this->inputs; }
    public function setInputs(?array $v): static { $this->inputs = $v; return $this; }
    public function getOutputs(): ?array { return $this->outputs; }
    public function setOutputs(?array $v): static { $this->outputs = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_WARNING]);
    }
}
