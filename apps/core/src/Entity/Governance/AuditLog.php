<?php

namespace App\Entity\Governance;

use App\Repository\Governance\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_user', columns: ['user_identifier'])]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
class AuditLog
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_READ = 'read';
    public const ACTION_EXECUTE = 'execute';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_EXPORT = 'export';
    public const ACTION_AI_QUERY = 'ai_query';
    public const ACTION_PIPELINE_RUN = 'pipeline_run';
    public const ACTION_PIPELINE_PAUSE = 'pipeline_pause';
    public const ACTION_SQL_EXECUTE = 'sql_execute';
    public const ACTION_DASHBOARD_VIEW = 'dashboard_view';
    public const ACTION_DATASET_ACCESS = 'dataset_access';
    public const ACTION_CONFIG_CHANGE = 'config_change';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(name: 'entity_type', length: 100, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(name: 'entity_id', length: 255, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(name: 'user_identifier', length: 255, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(name: 'before_value', type: Types::JSON, nullable: true)]
    private ?array $beforeValue = null;

    #[ORM\Column(name: 'after_value', type: Types::JSON, nullable: true)]
    private ?array $afterValue = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'tenant_id', nullable: true)]
    private ?int $tenantId = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(?string $entityType): static { $this->entityType = $entityType; return $this; }
    public function getEntityId(): ?string { return $this->entityId; }
    public function setEntityId(?string $entityId): static { $this->entityId = $entityId; return $this; }
    public function getUserIdentifier(): ?string { return $this->userIdentifier; }
    public function setUserIdentifier(?string $v): static { $this->userIdentifier = $v; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function getBeforeValue(): ?array { return $this->beforeValue; }
    public function setBeforeValue(?array $v): static { $this->beforeValue = $v; return $this; }
    public function getAfterValue(): ?array { return $this->afterValue; }
    public function setAfterValue(?array $v): static { $this->afterValue = $v; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ip): static { $this->ipAddress = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $v): static { $this->userAgent = $v; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }
    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $v): static { $this->tenantId = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
