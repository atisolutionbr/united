<?php

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\MetabaseConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MetabaseConfigRepository::class)]
#[ORM\Table(name: 'metabase_configs')]
#[ORM\HasLifecycleCallbacks]
class MetabaseConfig
{
    public const STATUS_UNTESTED = 'untested';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = 'Metabase Principal';

    #[ORM\Column(name: 'base_url', length: 512)]
    private string $baseUrl;

    #[ORM\Column(name: 'database_name', length: 255, nullable: true)]
    private ?string $databaseName = null;

    #[ORM\Column(name: 'username', length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(name: 'password_encrypted', length: 1024, nullable: true)]
    private ?string $passwordEncrypted = null;

    #[ORM\Column(name: 'secret_key', length: 512, nullable: true)]
    private ?string $secretKey = null;

    #[ORM\Column(name: 'connection_status', length: 30)]
    private string $connectionStatus = self::STATUS_UNTESTED;

    #[ORM\Column(name: 'last_tested_at', nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column(name: 'last_sync_at', nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'notes', type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getBaseUrl(): string { return $this->baseUrl; }
    public function setBaseUrl(string $baseUrl): static { $this->baseUrl = $baseUrl; return $this; }

    public function getDatabaseName(): ?string { return $this->databaseName; }
    public function setDatabaseName(?string $databaseName): static { $this->databaseName = $databaseName; return $this; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): static { $this->username = $username; return $this; }

    public function getPasswordEncrypted(): ?string { return $this->passwordEncrypted; }
    public function setPasswordEncrypted(?string $passwordEncrypted): static { $this->passwordEncrypted = $passwordEncrypted; return $this; }

    public function getSecretKey(): ?string { return $this->secretKey; }
    public function setSecretKey(?string $secretKey): static { $this->secretKey = $secretKey; return $this; }

    public function getConnectionStatus(): string { return $this->connectionStatus; }
    public function setConnectionStatus(string $connectionStatus): static { $this->connectionStatus = $connectionStatus; return $this; }

    public function getLastTestedAt(): ?\DateTimeImmutable { return $this->lastTestedAt; }
    public function setLastTestedAt(?\DateTimeImmutable $lastTestedAt): static { $this->lastTestedAt = $lastTestedAt; return $this; }

    public function getLastSyncAt(): ?\DateTimeImmutable { return $this->lastSyncAt; }
    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static { $this->lastSyncAt = $lastSyncAt; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
