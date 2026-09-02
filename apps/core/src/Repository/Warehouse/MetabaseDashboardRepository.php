<?php

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\MetabaseDashboard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MetabaseDashboardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MetabaseDashboard::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = true')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findEmbeddable(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = true')
            ->andWhere('d.allowEmbed = true')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
