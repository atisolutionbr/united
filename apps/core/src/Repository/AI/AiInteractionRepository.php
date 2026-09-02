<?php

namespace App\Repository\AI;

use App\Entity\AI\AiInteraction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiInteraction::class);
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countByProvider(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.provider, COUNT(i.id) as total')
            ->groupBy('i.provider')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $r) { $result[$r['provider']] = (int)$r['total']; }
        return $result;
    }

    public function countExternalToday(): int
    {
        $today = new \DateTimeImmutable('today');
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.isExternalProvider = true')
            ->andWhere('i.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();
    }

    public function getTotalTokens(): array
    {
        $row = $this->createQueryBuilder('i')
            ->select('SUM(i.tokensInput) as input, SUM(i.tokensOutput) as output')
            ->where('i.status = :s')
            ->setParameter('s', AiInteraction::STATUS_SUCCESS)
            ->getQuery()->getOneOrNullResult();
        return ['input' => (int)($row['input'] ?? 0), 'output' => (int)($row['output'] ?? 0)];
    }
}
