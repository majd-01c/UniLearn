<?php

namespace App\Repository;

use App\Entity\Schedule;
use App\Entity\Classe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schedule::class);
    }

    public function findByClasse(Classe $classe): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.classe = :classe')
            ->setParameter('classe', $classe)
            ->orderBy('s.dayOfWeek', 'ASC')
            ->addOrderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCurrentSchedule(Classe $classe): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.classe = :classe')
            ->andWhere('s.startDate <= :now')
            ->andWhere('s.endDate IS NULL OR s.endDate >= :now')
            ->setParameter('classe', $classe)
            ->setParameter('now', $now)
            ->orderBy('s.dayOfWeek', 'ASC')
            ->addOrderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
