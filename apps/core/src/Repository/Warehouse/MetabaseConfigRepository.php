<?php

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\MetabaseConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MetabaseConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MetabaseConfig::class);
    }

    public function findActive(): ?MetabaseConfig
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
