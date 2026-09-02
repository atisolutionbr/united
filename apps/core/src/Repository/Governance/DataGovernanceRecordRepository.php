<?php

namespace App\Repository\Governance;

use App\Entity\Governance\DataGovernanceRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DataGovernanceRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataGovernanceRecord::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->orderBy('r.datasetName', 'ASC')
            ->getQuery()->getResult();
    }

    public function countByClassification(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.classification, COUNT(r.id) as total')
            ->where('r.isActive = true')
            ->groupBy('r.classification')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) { $result[$row['classification']] = (int)$row['total']; }
        return $result;
    }
}
