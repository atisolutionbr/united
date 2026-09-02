<?php

namespace App\Repository\Operations;

use App\Entity\Operations\PipelineExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PipelineExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PipelineExecution::class);
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findByPipeline(int $pipelineId, int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.pipeline = :pid')
            ->setParameter('pid', $pipelineId)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.status, COUNT(e.id) as total')
            ->groupBy('e.status')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $r) { $result[$r['status']] = (int) $r['total']; }
        return $result;
    }

    public function countFailedToday(): int
    {
        $today = new \DateTimeImmutable('today');
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.status = :s')
            ->andWhere('e.createdAt >= :today')
            ->setParameter('s', PipelineExecution::STATUS_FAILED)
            ->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();
    }

    public function getAvgDurationByPipeline(int $pipelineId): ?float
    {
        $result = $this->createQueryBuilder('e')
            ->select('AVG(e.durationMs)')
            ->where('e.pipeline = :pid')
            ->andWhere('e.status = :s')
            ->setParameter('pid', $pipelineId)
            ->setParameter('s', PipelineExecution::STATUS_SUCCESS)
            ->getQuery()->getSingleScalarResult();
        return $result !== null ? round((float) $result) : null;
    }
}
