<?php

namespace App\Repository\Operations;

use App\Entity\Operations\Alert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :s')
            ->setParameter('s', Alert::STATUS_ACTIVE)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findActiveByLevel(string $level): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :s')
            ->andWhere('a.level = :l')
            ->setParameter('s', Alert::STATUS_ACTIVE)
            ->setParameter('l', $level)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :s')
            ->setParameter('s', Alert::STATUS_ACTIVE)
            ->getQuery()->getSingleScalarResult();
    }

    public function countCritical(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :s')
            ->andWhere('a.level = :l')
            ->setParameter('s', Alert::STATUS_ACTIVE)
            ->setParameter('l', Alert::LEVEL_CRITICAL)
            ->getQuery()->getSingleScalarResult();
    }

    public function findRecent(int $limit = 30): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
