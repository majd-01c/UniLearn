<?php

namespace App\Repository;

use App\Entity\ForumReply;
use App\Entity\ForumTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<ForumReply>
 */
class ForumReplyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumReply::class);
    }

    /**
     * Find replies for a topic with pagination
     */
    public function findByTopicPaginated(ForumTopic $topic, int $page = 1, int $limit = 20): Paginator
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.author', 'a')
            ->addSelect('a')
            ->where('r.topic = :topic')
            ->setParameter('topic', $topic)
            ->orderBy('r.createdAt', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), true);
    }

    /**
     * Find recent replies by user
     */
    public function findByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.topic', 't')
            ->addSelect('t')
            ->where('r.author = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count replies for a topic
     */
    public function countByTopic(ForumTopic $topic): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.topic = :topic')
            ->setParameter('topic', $topic)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count replies by user
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.author = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
