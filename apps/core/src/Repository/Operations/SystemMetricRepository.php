<?php

namespace App\Repository\Operations;

use App\Entity\Operations\SystemMetric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemMetric::class);
    }

    public function findLatestBySource(string $source): array
    {
        $metrics = $this->createQueryBuilder('m')
            ->where('m.source = :source')
            ->setParameter('source', $source)
            ->orderBy('m.recordedAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        // Return only the latest per metric_name
        $result = [];
        foreach ($metrics as $m) {
            if (!isset($result[$m->getMetricName()])) {
                $result[$m->getMetricName()] = $m;
            }
        }
        return array_values($result);
    }

    public function findLatestPerSource(): array
    {
        $sources = [SystemMetric::SOURCE_SYMFONY, SystemMetric::SOURCE_POSTGRES, SystemMetric::SOURCE_KESTRA, SystemMetric::SOURCE_OLLAMA, SystemMetric::SOURCE_QDRANT, SystemMetric::SOURCE_STORAGE];
        $result = [];
        foreach ($sources as $source) {
            $result[$source] = $this->findLatestBySource($source);
        }
        return $result;
    }

    public function pruneOlderThan(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.recordedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()->execute();
    }
}
