<?php

namespace App\Repository;

use App\Entity\Classe;
use App\Entity\TeacherClasse;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeacherClasse>
 */
class TeacherClasseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeacherClasse::class);
    }

    public function save(TeacherClasse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TeacherClasse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all classes for a teacher
     */
    public function findByTeacher(User $teacher): array
    {
        return $this->createQueryBuilder('tc')
            ->andWhere('tc.teacher = :teacher')
            ->andWhere('tc.isActive = true')
            ->setParameter('teacher', $teacher)
            ->join('tc.classe', 'c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all teachers in a class
     */
    public function findByClasse(Classe $classe): array
    {
        return $this->createQueryBuilder('tc')
            ->andWhere('tc.classe = :classe')
            ->setParameter('classe', $classe)
            ->join('tc.teacher', 't')
            ->orderBy('t.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a teacher is already assigned to a class
     */
    public function isTeacherAssigned(User $teacher, Classe $classe): bool
    {
        $result = $this->createQueryBuilder('tc')
            ->select('COUNT(tc.id)')
            ->andWhere('tc.teacher = :teacher')
            ->andWhere('tc.classe = :classe')
            ->setParameter('teacher', $teacher)
            ->setParameter('classe', $classe)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Count active teachers in a class
     */
    public function countActiveTeachersByClasse(Classe $classe): int
    {
        return (int) $this->createQueryBuilder('tc')
            ->select('COUNT(tc.id)')
            ->andWhere('tc.classe = :classe')
            ->andWhere('tc.isActive = true')
            ->setParameter('classe', $classe)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
