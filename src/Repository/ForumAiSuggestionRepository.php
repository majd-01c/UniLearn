<?php

namespace App\Repository;

use App\Entity\ForumAiSuggestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumAiSuggestion>
 */
class ForumAiSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumAiSuggestion::class);
    }

    /**
     * Find a cached suggestion by question hash
     */
    public function findCachedSuggestion(string $questionHash): ?ForumAiSuggestion
    {
        return $this->createQueryBuilder('s')
            ->where('s.questionHash = :hash')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('hash', $questionHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clean up expired suggestions
     */
    public function cleanupExpired(): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Get most popular cached suggestions
     */
    public function getMostUsed(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
