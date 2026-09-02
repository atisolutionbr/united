<?php

namespace App\Repository\AI;

use App\Entity\AI\AiPrompt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiPromptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiPrompt::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.name', 'ASC')
            ->getQuery()->getResult();
    }

    public function findByPurpose(string $purpose): ?AiPrompt
    {
        return $this->createQueryBuilder('p')
            ->where('p.purpose = :purpose')
            ->andWhere('p.isActive = true')
            ->setParameter('purpose', $purpose)
            ->orderBy('p.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
