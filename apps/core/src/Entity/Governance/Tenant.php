<?php

namespace App\Entity\Governance;

use App\Repository\Governance\TenantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\Table(name: 'tenants')]
#[ORM\HasLifecycleCallbacks]
class Tenant
{
    public const TYPE_PREFEITURA = 'prefeitura';
    public const TYPE_SECRETARIA = 'secretaria';
    public const TYPE_ORGAO = 'orgao';
    public const TYPE_AMBIENTE = 'ambiente';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 191, unique: true)]
    private string $slug;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_PREFEITURA;

    #[ORM\Column(name: 'municipio_id', length: 10, nullable: true)]
    private ?string $municipioId = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $estado = null;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $settings = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

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
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getMunicipioId(): ?string { return $this->municipioId; }
    public function setMunicipioId(?string $v): static { $this->municipioId = $v; return $this; }
    public function getEstado(): ?string { return $this->estado; }
    public function setEstado(?string $estado): static { $this->estado = $estado; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getSettings(): ?array { return $this->settings; }
    public function setSettings(?array $settings): static { $this->settings = $settings; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
