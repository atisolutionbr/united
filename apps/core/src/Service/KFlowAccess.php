<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\CompanyMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class KFlowAccess
{
    /** @var array<string, string> */
    private const MENUS = [
        'dashboard' => 'Visão Geral', 'products' => 'Produtos', 'clients' => 'Clientes',
        'suppliers' => 'Fornecedor', 'carriers' => 'Transportador', 'requisition' => 'Requisição',
        'approval' => 'Aprovação', 'connections' => 'Conectar ERP', 'intelligence' => 'Inteligência',
        'data' => 'Dados', 'integrations' => 'Integrações', 'ai' => 'IA', 'operations' => 'Operações',
        'governance' => 'Governança', 'platform' => 'Plataforma',
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly RequestStack $requestStack) {}
    /** @return array<string, string> */ public function menuOptions(): array { return self::MENUS; }
    public function isPlatformAdmin(?User $user): bool { return $user instanceof User && (in_array(User::ROLE_ADMIN, $user->getRoles(), true) || 'kadmin' === strtolower($user->getUserIdentifier())); }
    public function activeCompany(?User $user): ?Company
    {
        if (!$user instanceof User) return null;
        $session = $this->requestStack->getSession();
        $selectedId = $session?->get('kflow_company_id');
        $criteria = $this->isPlatformAdmin($user) ? ['isActive' => true] : ['user' => $user, 'isActive' => true];
        if ($this->isPlatformAdmin($user)) {
            $company = is_numeric($selectedId) ? $this->entityManager->getRepository(Company::class)->find((int) $selectedId) : null;
            if ($company instanceof Company && $company->isActive()) return $company;
            return $this->entityManager->getRepository(Company::class)->findOneBy(['isActive' => true], ['id' => 'ASC']);
        }
        $memberships = $this->entityManager->getRepository(CompanyMembership::class)->findBy($criteria, ['id' => 'ASC']);
        foreach ($memberships as $membership) if ($membership instanceof CompanyMembership && ((string) $membership->getCompany()->getId() === (string) $selectedId || null === $selectedId)) return $membership->getCompany();
        return null;
    }
    /** @return list<Company> */
    public function companies(?User $user): array
    {
        if (!$user instanceof User) return [];
        if ($this->isPlatformAdmin($user)) return $this->entityManager->getRepository(Company::class)->findBy(['isActive' => true], ['name' => 'ASC']);
        return array_map(static fn (CompanyMembership $membership): Company => $membership->getCompany(), $this->entityManager->getRepository(CompanyMembership::class)->findBy(['user' => $user, 'isActive' => true]));
    }
    public function can(?User $user, string $menu): bool
    {
        if ($this->isPlatformAdmin($user)) return true;
        $company = $this->activeCompany($user);
        if (!$user instanceof User || !$company instanceof Company) return false;
        $membership = $this->entityManager->getRepository(CompanyMembership::class)->findOneBy(['company' => $company, 'user' => $user, 'isActive' => true]);
        return $membership instanceof CompanyMembership && in_array($menu, $membership->getMenuPermissions(), true);
    }
}
