<?php

namespace App\Repository\AI;

use App\Entity\AI\AiEmbedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiEmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiEmbedding::class);
    }

    public function findBySource(string $type, string $sourceId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.sourceType = :type')
            ->andWhere('e.sourceId = :id')
            ->setParameter('type', $type)
            ->setParameter('id', $sourceId)
            ->getQuery()->getResult();
    }

    public function countBySourceType(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.sourceType, COUNT(e.id) as total')
            ->groupBy('e.sourceType')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $r) { $result[$r['sourceType']] = (int)$r['total']; }
        return $result;
    }
}
