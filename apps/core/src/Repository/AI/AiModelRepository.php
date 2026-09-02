<?php

namespace App\Repository\AI;

use App\Entity\AI\AiModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiModel::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = true')
            ->orderBy('m.isDefault', 'DESC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()->getResult();
    }

    public function findDefault(): ?AiModel
    {
        return $this->createQueryBuilder('m')
            ->where('m.isDefault = true')
            ->andWhere('m.isActive = true')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    public function findLocalModels(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.provider = :p')
            ->setParameter('p', AiModel::PROVIDER_OLLAMA)
            ->andWhere('m.isActive = true')
            ->getQuery()->getResult();
    }

    public function countByProvider(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.provider, COUNT(m.id) as total')
            ->where('m.isActive = true')
            ->groupBy('m.provider')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $r) { $result[$r['provider']] = (int)$r['total']; }
        return $result;
    }
}
