<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\CompanyMembership;
use App\Entity\ErpConnection;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class KFlowAccess
{
    /** @var array<string, string> */
    private const MENUS = [
        'dashboard' => 'Visão Geral', 'products' => 'Produtos', 'clients' => 'Clientes',
        'suppliers' => 'Fornecedor', 'carriers' => 'Transportador', 'requisition' => 'Requisição',
        'approval' => 'Aprovação', 'connections' => 'Conectar ERP',
    ];

    /** @var array<int, Company|null> */ private array $activeCompanies = [];
    /** @var array<int, list<Company>> */ private array $companies = [];
    /** @var array<int, list<string>> */ private array $erpNames = [];
    /** @var array<string, CompanyMembership|null> */ private array $memberships = [];
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly RequestStack $requestStack) {}
    /** @return array<string, string> */ public function menuOptions(): array { return self::MENUS; }
    public function isPlatformAdmin(?User $user): bool { return $user instanceof User && (in_array(User::ROLE_ADMIN, $user->getRoles(), true) || 'kadmin' === strtolower($user->getUserIdentifier())); }
    public function activeCompany(?User $user): ?Company
    {
        if (!$user instanceof User) return null;
        $userId = $user->getId() ?? 0;
        if (array_key_exists($userId, $this->activeCompanies)) return $this->activeCompanies[$userId];
        $session = $this->requestStack->getSession();
        $selectedId = $session?->get('kflow_company_id');
        $criteria = $this->isPlatformAdmin($user) ? ['isActive' => true] : ['user' => $user, 'isActive' => true];
        if ($this->isPlatformAdmin($user)) {
            $company = is_numeric($selectedId) ? $this->entityManager->getRepository(Company::class)->find((int) $selectedId) : null;
            if ($company instanceof Company && $company->isActive()) return $this->activeCompanies[$userId] = $company;
            return $this->activeCompanies[$userId] = $this->entityManager->getRepository(Company::class)->findOneBy(['isActive' => true], ['id' => 'ASC']);
        }
        $memberships = $this->entityManager->getRepository(CompanyMembership::class)->findBy($criteria, ['id' => 'ASC']);
        foreach ($memberships as $membership) if ($membership instanceof CompanyMembership && ((string) $membership->getCompany()->getId() === (string) $selectedId || null === $selectedId)) return $this->activeCompanies[$userId] = $membership->getCompany();
        return $this->activeCompanies[$userId] = null;
    }
    /** @return list<Company> */
    public function companies(?User $user): array
    {
        if (!$user instanceof User) return [];
        $userId = $user->getId() ?? 0;
        if (isset($this->companies[$userId])) return $this->companies[$userId];
        if ($this->isPlatformAdmin($user)) return $this->companies[$userId] = $this->entityManager->getRepository(Company::class)->findBy(['isActive' => true], ['name' => 'ASC']);
        return $this->companies[$userId] = array_map(static fn (CompanyMembership $membership): Company => $membership->getCompany(), $this->entityManager->getRepository(CompanyMembership::class)->findBy(['user' => $user, 'isActive' => true]));
    }
    public function can(?User $user, string $menu): bool
    {
        if ($this->isPlatformAdmin($user)) return true;
        $company = $this->activeCompany($user);
        if (!$user instanceof User || !$company instanceof Company) return false;
        $membership = $this->membership($company, $user);
        if (!$membership instanceof CompanyMembership) return false;
        if ('erp' === $membership->getAccessOrigin() && 'connections' === $menu) return false;
        return in_array($menu, $membership->getMenuPermissions(), true);
    }
    /** @return list<string> */
    public function availableErps(?User $user): array
    {
        $company = $this->activeCompany($user);
        if (!$user instanceof User || !$company instanceof Company) return [];
        $companyId = $company->getId() ?? 0;
        if (isset($this->erpNames[$companyId])) return $this->erpNames[$companyId];
        return $this->erpNames[$companyId] = array_map(static fn (ErpConnection $connection): string => $connection->getErpName(), $this->entityManager->getRepository(ErpConnection::class)->findBy(['company' => $company, 'isActive' => true], ['erpName' => 'ASC']));
    }
    public function activeErp(?User $user): ?string
    {
        $erps = $this->availableErps($user);
        $selected = (string) ($this->requestStack->getSession()?->get('kflow_selected_erp') ?? '');
        if (in_array($selected, $erps, true)) return $selected;
        return 1 === count($erps) ? $erps[0] : null;
    }
    private function membership(Company $company, User $user): ?CompanyMembership
    {
        $key = sprintf('%d:%d', $company->getId(), $user->getId());
        if (!array_key_exists($key, $this->memberships)) $this->memberships[$key] = $this->entityManager->getRepository(CompanyMembership::class)->findOneBy(['company' => $company, 'user' => $user, 'isActive' => true]);
        return $this->memberships[$key];
    }
}
