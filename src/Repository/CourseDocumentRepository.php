<?php

namespace App\Repository;

use App\Entity\Classe;
use App\Entity\CourseDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CourseDocument>
 */
class CourseDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseDocument::class);
    }

    public function save(CourseDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CourseDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all documents for a class
     */
    public function findByClasse(Classe $classe): array
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.classe = :classe')
            ->andWhere('cd.isActive = true')
            ->setParameter('classe', $classe)
            ->orderBy('cd.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active documents
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.isActive = true')
            ->join('cd.classe', 'c')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('cd.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
