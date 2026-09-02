<?php

namespace App\Repository;

use App\Entity\DataProvider;
use App\Entity\ProviderPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProviderPackage>
 */
final class ProviderPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderPackage::class);
    }

    public function findOneByProviderAndPackageId(DataProvider $provider, string $packageId): ?ProviderPackage
    {
        return $this->findOneBy([
            'dataProvider' => $provider,
            'packageId' => $packageId,
        ]);
    }

    /**
     * @return list<ProviderPackage>
     */
    public function findByProviderOrdered(DataProvider $provider): array
    {
        return $this->createQueryBuilder('package')
            ->andWhere('package.dataProvider = :provider')
            ->setParameter('provider', $provider)
            ->orderBy('package.isMonitored', 'DESC')
            ->addOrderBy('package.updatedAt', 'DESC')
            ->addOrderBy('package.packageId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countMonitored(): int
    {
        return (int) $this->createQueryBuilder('package')
            ->select('COUNT(package.id)')
            ->andWhere('package.isMonitored = :isMonitored')
            ->setParameter('isMonitored', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAllPackages(): int
    {
        return (int) $this->createQueryBuilder('package')
            ->select('COUNT(package.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}