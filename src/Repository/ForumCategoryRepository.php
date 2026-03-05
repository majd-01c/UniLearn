<?php

namespace App\Repository;

use App\Entity\ForumCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumCategory>
 */
class ForumCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumCategory::class);
    }

    /**
     * @return ForumCategory[] Returns an array of active ForumCategory objects ordered by position
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ForumCategory[] Returns all categories with topic counts
     */
    public function findAllWithStats(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.topics', 't')
            ->addSelect('COUNT(t.id) as topicCount')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
