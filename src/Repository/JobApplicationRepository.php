<?php

namespace App\Repository;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobApplication>
 */
class JobApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobApplication::class);
    }

    /**
     * @return JobApplication[]
     */
    public function findByOffer(JobOffer $offer): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.offer = :offer')
            ->setParameter('offer', $offer)
            ->leftJoin('a.student', 's')
            ->addSelect('s')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasStudentApplied(JobOffer $offer, User $student): bool
    {
        return (bool) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.offer = :offer')
            ->andWhere('a.student = :student')
            ->setParameter('offer', $offer)
            ->setParameter('student', $student)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
