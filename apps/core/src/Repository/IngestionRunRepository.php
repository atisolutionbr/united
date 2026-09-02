<?php

namespace App\Repository;

use App\Entity\IngestionRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IngestionRun>
 */
final class IngestionRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IngestionRun::class);
    }

    /**
     * @return list<IngestionRun>
     */
    public function findRecentRuns(int $limit = 20): array
    {
        return $this->createQueryBuilder('run')
            ->orderBy('run.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}