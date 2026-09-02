<?php

namespace App\Repository\Operations;

use App\Entity\Operations\Pipeline;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PipelineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pipeline::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.name', 'ASC')
            ->getQuery()->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->setParameter('type', $type)
            ->orderBy('p.name', 'ASC')
            ->getQuery()->getResult();
    }

    public function countByStatus(): array
    {
        $all = $this->findAll();
        $result = ['active' => 0, 'failed' => 0, 'paused' => 0, 'never_run' => 0];
        foreach ($all as $p) {
            $status = $p->getLastExecutionStatus() ?? 'never_run';
            if ($status === 'SUCCESS') { $result['active']++; }
            elseif ($status === 'FAILED') { $result['failed']++; }
            elseif (!$p->isActive()) { $result['paused']++; }
            else { $result['never_run']++; }
        }
        return $result;
    }

    public function findWithRecentFailures(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.lastExecutionStatus = :s')
            ->setParameter('s', 'FAILED')
            ->orderBy('p.lastExecutedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
