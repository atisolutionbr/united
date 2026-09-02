<?php

namespace App\Repository\Governance;

use App\Entity\Governance\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findByUser(string $userIdentifier, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.userIdentifier = :u')
            ->setParameter('u', $userIdentifier)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findByAction(string $action, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.action = :action')
            ->setParameter('action', $action)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countByActionToday(): array
    {
        $today = new \DateTimeImmutable('today');
        $rows = $this->createQueryBuilder('a')
            ->select('a.action, COUNT(a.id) as total')
            ->where('a.createdAt >= :today')
            ->setParameter('today', $today)
            ->groupBy('a.action')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $r) { $result[$r['action']] = (int)$r['total']; }
        return $result;
    }
}
