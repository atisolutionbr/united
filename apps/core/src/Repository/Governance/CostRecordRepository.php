<?php

namespace App\Repository\Governance;

use App\Entity\Governance\CostRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CostRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CostRecord::class);
    }

    public function getTotalByService(\DateTimeImmutable $from = null, \DateTimeImmutable $to = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.service, SUM(c.totalCostUsd) as total');
        if ($from) { $qb->andWhere('c.periodDate >= :from')->setParameter('from', $from); }
        if ($to) { $qb->andWhere('c.periodDate <= :to')->setParameter('to', $to); }
        $rows = $qb->groupBy('c.service')->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $r) { $result[$r['service']] = round((float)$r['total'], 4); }
        return $result;
    }

    public function getTotalThisMonth(): float
    {
        $firstDay = new \DateTimeImmutable('first day of this month');
        $rows = $this->createQueryBuilder('c')
            ->select('SUM(c.totalCostUsd) as total')
            ->where('c.periodDate >= :from')
            ->setParameter('from', $firstDay)
            ->getQuery()->getSingleScalarResult();
        return round((float)($rows ?? 0), 4);
    }

    public function getDailyTotals(int $days = 30): array
    {
        $from = new \DateTimeImmutable("-{$days} days");
        return $this->createQueryBuilder('c')
            ->select('c.periodDate, c.service, SUM(c.totalCostUsd) as total')
            ->where('c.periodDate >= :from')
            ->setParameter('from', $from)
            ->groupBy('c.periodDate', 'c.service')
            ->orderBy('c.periodDate', 'ASC')
            ->getQuery()->getArrayResult();
    }
}
