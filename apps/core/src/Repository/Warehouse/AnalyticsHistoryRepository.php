<?php

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\AnalyticsHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticsHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsHistory::class);
    }

    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByEventType(string $eventType, int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.eventType = :eventType')
            ->setParameter('eventType', $eventType)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
