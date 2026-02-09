<?php

namespace App\Repository;

use App\Entity\ForumTopic;
use App\Entity\ForumCategory;
use App\Enum\TopicStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<ForumTopic>
 */
class ForumTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumTopic::class);
    }

    /**
     * Find topics with pagination
     */
    public function findPaginated(int $page = 1, int $limit = 15, ?ForumCategory $category = null, ?string $search = null): Paginator
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.author', 'a')
            ->addSelect('c', 'a')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.isPinned', 'DESC')
            ->addOrderBy('t.lastActivityAt', 'DESC');

        if ($category) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $category);
        }

        if ($search) {
            $qb->andWhere('t.title LIKE :search OR t.content LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), true);
    }

    /**
     * Find recent topics
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.author', 'a')
            ->addSelect('c', 'a')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find topics by user
     */
    public function findByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c')
            ->where('t.author = :userId')
            ->andWhere('c.isActive = :active')
            ->setParameter('userId', $userId)
            ->setParameter('active', true)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unanswered topics
     */
    public function findUnanswered(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.replies', 'r')
            ->addSelect('c')
            ->where('c.isActive = :active')
            ->andWhere('t.status = :status')
            ->setParameter('active', true)
            ->setParameter('status', TopicStatus::OPEN)
            ->groupBy('t.id')
            ->having('COUNT(r.id) = 0')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count topics by category
     */
    public function countByCategory(ForumCategory $category): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
