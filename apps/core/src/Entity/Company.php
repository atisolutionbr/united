<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'company')]
#[ORM\HasLifecycleCallbacks]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(name: 'legal_name', length: 220, nullable: true)]
    private ?string $legalName = null;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $document = null;

    #[ORM\Column(name: 'holding_name', length: 160, nullable: true)]
    private ?string $holdingName = null;

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getLegalName(): ?string { return $this->legalName; }
    public function setLegalName(?string $legalName): static { $this->legalName = $legalName; return $this; }
    public function getDocument(): ?string { return $this->document; }
    public function setDocument(?string $document): static { $this->document = $document; return $this; }
    public function getHoldingName(): ?string { return $this->holdingName; }
    public function setHoldingName(?string $holdingName): static { $this->holdingName = $holdingName; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
}
