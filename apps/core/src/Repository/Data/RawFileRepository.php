<?php

namespace App\Repository\Data;

use App\Entity\Data\RawFile;
use App\Entity\DatasetResource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RawFile>
 */
final class RawFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RawFile::class);
    }

    public function findOneByHash(string $hash): ?RawFile
    {
        return $this->createQueryBuilder('raw_file')
            ->andWhere('raw_file.fileHash = :hash')
            ->andWhere('raw_file.downloadStatus IN (:statuses)')
            ->setParameter('hash', $hash)
            ->setParameter('statuses', [RawFile::STATUS_DOWNLOADED, RawFile::STATUS_DUPLICATE])
            ->orderBy('raw_file.downloadedAt', 'DESC')
            ->addOrderBy('raw_file.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestForResource(DatasetResource $resource): ?RawFile
    {
        return $this->createQueryBuilder('raw_file')
            ->andWhere('raw_file.datasetResource = :resource')
            ->setParameter('resource', $resource)
            ->orderBy('raw_file.downloadedAt', 'DESC')
            ->addOrderBy('raw_file.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByLocalPath(string $localPath): int
    {
        return (int) $this->createQueryBuilder('raw_file')
            ->select('COUNT(raw_file.id)')
            ->andWhere('raw_file.localPath = :localPath')
            ->setParameter('localPath', $localPath)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPreviewable(): int
    {
        return (int) $this->createQueryBuilder('raw_file')
            ->select('COUNT(raw_file.id)')
            ->andWhere('LOWER(raw_file.extension) IN (:extensions)')
            ->setParameter('extensions', ['csv', 'xlsx'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<RawFile>
     */
    public function findPreviewableRecent(int $limit = 30): array
    {
        return $this->createQueryBuilder('raw_file')
            ->andWhere('LOWER(raw_file.extension) IN (:extensions)')
            ->setParameter('extensions', ['csv', 'xlsx'])
            ->orderBy('raw_file.downloadedAt', 'DESC')
            ->addOrderBy('raw_file.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<RawFile>
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('raw_file')
            ->orderBy('raw_file.downloadedAt', 'DESC')
            ->addOrderBy('raw_file.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<RawFile>
     */
    public function findWithStaging(int $limit = 50): array
    {
        return $this->createQueryBuilder('raw_file')
            ->andWhere('raw_file.stagingPath IS NOT NULL')
            ->orderBy('raw_file.downloadedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countWithStaging(): int
    {
        return (int) $this->createQueryBuilder('raw_file')
            ->select('COUNT(raw_file.id)')
            ->andWhere('raw_file.stagingPath IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTransformationFailed(): int
    {
        return (int) $this->createQueryBuilder('raw_file')
            ->select('COUNT(raw_file.id)')
            ->andWhere('raw_file.transformationStatus = :status')
            ->setParameter('status', RawFile::TRANSFORMATION_FAILED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
