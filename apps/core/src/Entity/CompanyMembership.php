<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'company_membership')]
#[ORM\UniqueConstraint(name: 'UNIQ_COMPANY_MEMBERSHIP', columns: ['company_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class CompanyMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'company_id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'erp_identifier', length: 160, nullable: true)]
    private ?string $erpIdentifier = null;

    #[ORM\Column(name: 'erp_display_name', length: 220, nullable: true)]
    private ?string $erpDisplayName = null;

    #[ORM\Column(name: 'erp_email', length: 180, nullable: true)]
    private ?string $erpEmail = null;

    /** @var list<string> */
    #[ORM\Column(name: 'menu_permissions', type: Types::JSON)]
    private array $menuPermissions = [];

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(Company $company, User $user)
    {
        $this->company = $company;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void { $this->updatedAt = new \DateTimeImmutable(); }
    public function getCompany(): Company { return $this->company; }
    public function getUser(): User { return $this->user; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    /** @return list<string> */ public function getMenuPermissions(): array { return $this->menuPermissions; }
    /** @param list<string> $permissions */ public function setMenuPermissions(array $permissions): static { $this->menuPermissions = array_values(array_unique($permissions)); return $this; }
    public function setErpIdentity(?string $identifier, ?string $name, ?string $email): static { $this->erpIdentifier = $identifier; $this->erpDisplayName = $name; $this->erpEmail = $email; return $this; }
}
