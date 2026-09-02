<?php

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\AnalyticModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticModel::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = true')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySourceTable(string $table): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.sourceTable = :table')
            ->setParameter('table', $table)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countReady(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.lastRefreshStatus = :status')
            ->setParameter('status', AnalyticModel::STATUS_READY)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
