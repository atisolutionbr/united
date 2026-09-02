<?php

namespace App\Repository\Data;

use App\Entity\Data\DatasetColumnMapping;
use App\Entity\ProviderPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DatasetColumnMapping>
 */
final class DatasetColumnMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DatasetColumnMapping::class);
    }

    /**
     * @return list<DatasetColumnMapping>
     */
    public function findByPackageOrdered(ProviderPackage $package): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.providerPackage = :package')
            ->setParameter('package', $package)
            ->orderBy('m.normalizedColumn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<DatasetColumnMapping>
     */
    public function findActiveByPackage(ProviderPackage $package): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.providerPackage = :package')
            ->andWhere('m.isActive = true')
            ->setParameter('package', $package)
            ->orderBy('m.normalizedColumn', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByPackage(ProviderPackage $package): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.providerPackage = :package')
            ->setParameter('package', $package)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
