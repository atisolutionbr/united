<?php

namespace App\Repository\AI;

use App\Entity\AI\AiAgent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiAgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiAgent::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.isActive = true')
            ->orderBy('a.name', 'ASC')
            ->getQuery()->getResult();
    }
}
