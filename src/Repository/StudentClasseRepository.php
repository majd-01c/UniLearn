<?php

namespace App\Repository;

use App\Entity\Classe;
use App\Entity\StudentClasse;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentClasse>
 */
class StudentClasseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentClasse::class);
    }

    public function save(StudentClasse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(StudentClasse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all classes for a student
     */
    public function findByStudent(User $student): array
    {
        return $this->createQueryBuilder('sc')
            ->andWhere('sc.student = :student')
            ->andWhere('sc.isActive = true')
            ->setParameter('student', $student)
            ->join('sc.classe', 'c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all students in a class
     */
    public function findByClasse(Classe $classe): array
    {
        return $this->createQueryBuilder('sc')
            ->andWhere('sc.classe = :classe')
            ->andWhere('sc.isActive = true')
            ->setParameter('classe', $classe)
            ->join('sc.student', 's')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if student is already enrolled in class
     */
    public function isStudentEnrolled(User $student, Classe $classe): bool
    {
        $result = $this->createQueryBuilder('sc')
            ->select('COUNT(sc.id)')
            ->andWhere('sc.student = :student')
            ->andWhere('sc.classe = :classe')
            ->andWhere('sc.isActive = true')
            ->setParameter('student', $student)
            ->setParameter('classe', $classe)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Count students in a class
     */
    public function countStudentsInClasse(Classe $classe): int
    {
        return (int) $this->createQueryBuilder('sc')
            ->select('COUNT(sc.id)')
            ->andWhere('sc.classe = :classe')
            ->andWhere('sc.isActive = true')
            ->setParameter('classe', $classe)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
