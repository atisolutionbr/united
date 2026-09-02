<?php

namespace App\Repository;

use App\Entity\DatasetResource;
use App\Entity\ProviderPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DatasetResource>
 */
final class DatasetResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DatasetResource::class);
    }

    public function findOneByPackageAndResourceId(ProviderPackage $package, string $resourceId): ?DatasetResource
    {
        return $this->findOneBy([
            'providerPackage' => $package,
            'resourceId' => $resourceId,
        ]);
    }
}