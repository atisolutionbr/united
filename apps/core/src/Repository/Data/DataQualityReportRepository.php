<?php

namespace App\Repository\Data;

use App\Entity\Data\DataQualityReport;
use App\Entity\Data\RawFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DataQualityReport>
 */
final class DataQualityReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataQualityReport::class);
    }

    public function findLatestForRawFile(RawFile $rawFile): ?DataQualityReport
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.rawFile = :rawFile')
            ->setParameter('rawFile', $rawFile)
            ->orderBy('r.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<DataQualityReport>
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.generatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countWithIssues(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.invalidRows > 0 OR r.duplicatedRows > 0')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getAverageQualityScore(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.validRows) as validSum, SUM(r.totalRows) as totalSum')
            ->andWhere('r.totalRows > 0')
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result || (int) $result['totalSum'] === 0) {
            return 0.0;
        }

        return round(((int) $result['validSum'] / (int) $result['totalSum']) * 100, 1);
    }
}
