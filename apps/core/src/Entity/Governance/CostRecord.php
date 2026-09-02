<?php

namespace App\Entity\Governance;

use App\Repository\Governance\CostRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CostRecordRepository::class)]
#[ORM\Table(name: 'cost_records')]
#[ORM\Index(name: 'idx_cost_service', columns: ['service'])]
#[ORM\Index(name: 'idx_cost_period', columns: ['period_date'])]
class CostRecord
{
    public const SERVICE_OPENAI = 'openai';
    public const SERVICE_EMBEDDINGS = 'embeddings';
    public const SERVICE_WAREHOUSE = 'warehouse';
    public const SERVICE_STORAGE = 'storage';
    public const SERVICE_PIPELINE = 'pipeline';
    public const SERVICE_METABASE = 'metabase';
    public const SERVICE_KESTRA = 'kestra';
    public const SERVICE_OLLAMA = 'ollama';
    public const SERVICE_QDRANT = 'qdrant';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $service;

    #[ORM\Column(name: 'period_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $periodDate;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 4)]
    private string $quantity = '0';

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(name: 'unit_cost_usd', type: Types::DECIMAL, precision: 10, scale: 8)]
    private string $unitCostUsd = '0';

    #[ORM\Column(name: 'total_cost_usd', type: Types::DECIMAL, precision: 10, scale: 6)]
    private string $totalCostUsd = '0';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'tenant_id', nullable: true)]
    private ?int $tenantId = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->periodDate = new \DateTimeImmutable('today');
    }

    public function getId(): ?int { return $this->id; }
    public function getService(): string { return $this->service; }
    public function setService(string $service): static { $this->service = $service; return $this; }
    public function getPeriodDate(): \DateTimeImmutable { return $this->periodDate; }
    public function setPeriodDate(\DateTimeImmutable $v): static { $this->periodDate = $v; return $this; }
    public function getQuantity(): float { return (float) $this->quantity; }
    public function setQuantity(float $v): static { $this->quantity = (string) $v; return $this; }
    public function getUnit(): ?string { return $this->unit; }
    public function setUnit(?string $unit): static { $this->unit = $unit; return $this; }
    public function getUnitCostUsd(): float { return (float) $this->unitCostUsd; }
    public function setUnitCostUsd(float $v): static { $this->unitCostUsd = (string) $v; return $this; }
    public function getTotalCostUsd(): float { return (float) $this->totalCostUsd; }
    public function setTotalCostUsd(float $v): static { $this->totalCostUsd = (string) $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }
    public function getTenantId(): ?int { return $this->tenantId; }
    public function setTenantId(?int $v): static { $this->tenantId = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
