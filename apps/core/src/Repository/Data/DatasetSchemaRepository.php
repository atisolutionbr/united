<?php

namespace App\Repository\Data;

use App\Entity\Data\DatasetSchema;
use App\Entity\Data\RawFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DatasetSchema>
 */
final class DatasetSchemaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DatasetSchema::class);
    }

    /**
     * @return list<DatasetSchema>
     */
    public function findByRawFileOrdered(RawFile $rawFile): array
    {
        return $this->createQueryBuilder('schema')
            ->andWhere('schema.rawFile = :rawFile')
            ->setParameter('rawFile', $rawFile)
            ->orderBy('schema.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}