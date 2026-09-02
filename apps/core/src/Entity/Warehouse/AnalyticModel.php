<?php

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\AnalyticModelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalyticModelRepository::class)]
#[ORM\Table(name: 'analytic_models')]
#[ORM\Index(name: 'idx_analytic_models_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_analytic_models_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class AnalyticModel
{
    public const REFRESH_MANUAL = 'manual';
    public const REFRESH_DAILY = 'daily';
    public const REFRESH_HOURLY = 'hourly';
    public const REFRESH_WEEKLY = 'weekly';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

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

    #[ORM\Column(name: 'source_table', length: 255)]
    private string $sourceTable;

    #[ORM\Column(name: 'target_table', length: 255)]
    private string $targetTable;

    #[ORM\Column(type: Types::JSON)]
    private array $dimensions = [];

    #[ORM\Column(type: Types::JSON)]
    private array $metrics = [];

    #[ORM\Column(type: Types::JSON)]
    private array $filters = [];

    #[ORM\Column(name: 'refresh_strategy', length: 30)]
    private string $refreshStrategy = self::REFRESH_MANUAL;

    #[ORM\Column(name: 'last_refresh_status', length: 30, nullable: true)]
    private ?string $lastRefreshStatus = null;

    #[ORM\Column(name: 'last_refreshed_at', nullable: true)]
    private ?\DateTimeImmutable $lastRefreshedAt = null;

    #[ORM\Column(name: 'row_count', nullable: true)]
    private ?int $rowCount = null;

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

    public function getSourceTable(): string { return $this->sourceTable; }
    public function setSourceTable(string $sourceTable): static { $this->sourceTable = $sourceTable; return $this; }

    public function getTargetTable(): string { return $this->targetTable; }
    public function setTargetTable(string $targetTable): static { $this->targetTable = $targetTable; return $this; }

    public function getDimensions(): array { return $this->dimensions; }
    public function setDimensions(array $dimensions): static { $this->dimensions = $dimensions; return $this; }

    public function getMetrics(): array { return $this->metrics; }
    public function setMetrics(array $metrics): static { $this->metrics = $metrics; return $this; }

    public function getFilters(): array { return $this->filters; }
    public function setFilters(array $filters): static { $this->filters = $filters; return $this; }

    public function getRefreshStrategy(): string { return $this->refreshStrategy; }
    public function setRefreshStrategy(string $refreshStrategy): static { $this->refreshStrategy = $refreshStrategy; return $this; }

    public function getLastRefreshStatus(): ?string { return $this->lastRefreshStatus; }
    public function setLastRefreshStatus(?string $lastRefreshStatus): static { $this->lastRefreshStatus = $lastRefreshStatus; return $this; }

    public function getLastRefreshedAt(): ?\DateTimeImmutable { return $this->lastRefreshedAt; }
    public function setLastRefreshedAt(?\DateTimeImmutable $lastRefreshedAt): static { $this->lastRefreshedAt = $lastRefreshedAt; return $this; }

    public function getRowCount(): ?int { return $this->rowCount; }
    public function setRowCount(?int $rowCount): static { $this->rowCount = $rowCount; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public static function getRefreshStrategies(): array
    {
        return [self::REFRESH_MANUAL, self::REFRESH_DAILY, self::REFRESH_HOURLY, self::REFRESH_WEEKLY];
    }
}
