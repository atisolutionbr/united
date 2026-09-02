<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_connection')]
#[ORM\HasLifecycleCallbacks]
class ErpConnection
{
    public const METHOD_DATABASE = 'database';
    public const METHOD_API = 'api';
    public const METHOD_WEBSERVICE = 'webservice';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'erp_name', length: 64, unique: true)]
    private string $erpName;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = false;

    #[ORM\Column(name: 'connection_method', length: 32, nullable: true)]
    private ?string $connectionMethod = null;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'connection_settings', type: Types::JSON)]
    private array $connectionSettings = [];

    /** @var array<string, string> */
    #[ORM\Column(name: 'product_mapping', type: Types::JSON)]
    private array $productMapping = [];

    #[ORM\Column(name: 'configured_at', nullable: true)]
    private ?\DateTimeImmutable $configuredAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $erpName)
    {
        $this->erpName = $erpName;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getErpName(): string
    {
        return $this->erpName;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getConnectionMethod(): ?string
    {
        return $this->connectionMethod;
    }

    public function setConnectionMethod(?string $connectionMethod): static
    {
        $this->connectionMethod = $connectionMethod;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getConnectionSettings(): array
    {
        return $this->connectionSettings;
    }

    /** @param array<string, mixed> $connectionSettings */
    public function setConnectionSettings(array $connectionSettings): static
    {
        $this->connectionSettings = $connectionSettings;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSettingsForMethod(string $method): array
    {
        $profiles = $this->connectionSettings['profiles'] ?? null;
        if (is_array($profiles) && isset($profiles[$method]) && is_array($profiles[$method])) {
            return $profiles[$method];
        }

        if ($this->connectionMethod === $method && !isset($this->connectionSettings['profiles'])) {
            return $this->connectionSettings;
        }

        return [];
    }

    /** @param array<string, mixed> $settings */
    public function setSettingsForMethod(string $method, array $settings): static
    {
        $profiles = $this->connectionSettings['profiles'] ?? [];
        if (!is_array($profiles)) {
            $profiles = [];
        }

        if ([] === $profiles && null !== $this->connectionMethod && !isset($this->connectionSettings['profiles'])) {
            $profiles[$this->connectionMethod] = $this->connectionSettings;
        }

        $profiles[$method] = $settings;
        $this->connectionSettings = ['profiles' => $profiles];

        return $this;
    }

    /** @return array<string, string> */
    public function getProductMapping(): array
    {
        return $this->productMapping;
    }

    /** @param array<string, string> $productMapping */
    public function setProductMapping(array $productMapping): static
    {
        $this->productMapping = $productMapping;

        return $this;
    }

    public function markConfigured(): static
    {
        $this->configuredAt = new \DateTimeImmutable();

        return $this;
    }

    public function getConfiguredAt(): ?\DateTimeImmutable
    {
        return $this->configuredAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
