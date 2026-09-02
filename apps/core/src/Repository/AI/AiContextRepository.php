<?php

namespace App\Repository\AI;

use App\Entity\AI\AiContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiContextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiContext::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()->getResult();
    }
}
