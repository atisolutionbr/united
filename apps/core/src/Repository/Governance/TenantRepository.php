<?php

namespace App\Repository\Governance;

use App\Entity\Governance\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->orderBy('t.name', 'ASC')
            ->getQuery()->getResult();
    }
}
