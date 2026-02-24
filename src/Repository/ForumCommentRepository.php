<?php

namespace App\Repository;

use App\Entity\ForumComment;
use App\Entity\ForumTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<ForumComment>
 */
class ForumCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumComment::class);
    }

    /**
     * Find top-level comments for a topic with pagination (excludes replies to comments)
     */
    public function findByTopicPaginated(ForumTopic $topic, int $page = 1, int $limit = 20): Paginator
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->leftJoin('c.replies', 'r')
            ->addSelect('r')
            ->where('c.topic = :topic')
            ->andWhere('c.parent IS NULL')
            ->setParameter('topic', $topic)
            ->orderBy('c.createdAt', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), true);
    }

    /**
     * Find all comments for a topic (including replies)
     */
    public function findAllByTopic(ForumTopic $topic): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->addSelect('a')
            ->where('c.topic = :topic')
            ->setParameter('topic', $topic)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent comments by user
     */
    public function findByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.topic', 't')
            ->addSelect('t')
            ->where('c.author = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count comments for a topic (top-level only)
     */
    public function countByTopic(ForumTopic $topic): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.topic = :topic')
            ->andWhere('c.parent IS NULL')
            ->setParameter('topic', $topic)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count all comments by user (including replies)
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.author = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
