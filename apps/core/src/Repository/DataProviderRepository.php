<?php

namespace App\Repository;

use App\Entity\DataProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DataProvider>
 */
final class DataProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataProvider::class);
    }

    /**
     * @return list<DataProvider>
     */
    public function findOrderedForDashboard(): array
    {
        return $this->createQueryBuilder('provider')
            ->orderBy('provider.isActive', 'DESC')
            ->addOrderBy('provider.updatedAt', 'DESC')
            ->addOrderBy('provider.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveProviders(): int
    {
        return (int) $this->createQueryBuilder('provider')
            ->select('COUNT(provider.id)')
            ->andWhere('provider.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}