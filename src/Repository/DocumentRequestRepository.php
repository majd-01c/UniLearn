<?php

namespace App\Repository;

use App\Entity\DocumentRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentRequest::class);
    }

    public function findByStudent(User $student): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.student = :student')
            ->setParameter('student', $student)
            ->orderBy('d.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingRequests(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('d.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
