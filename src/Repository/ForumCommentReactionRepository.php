<?php

namespace App\Repository;

use App\Entity\ForumComment;
use App\Entity\ForumCommentReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumCommentReaction>
 */
class ForumCommentReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumCommentReaction::class);
    }

    /**
     * Find a user's reaction to a specific comment
     */
    public function findUserReaction(User $user, ForumComment $comment): ?ForumCommentReaction
    {
        return $this->findOneBy([
            'user' => $user,
            'comment' => $comment,
        ]);
    }

    /**
     * Count likes for a comment
     */
    public function countLikes(ForumComment $comment): int
    {
        return $this->count([
            'comment' => $comment,
            'type' => 'like',
        ]);
    }

    /**
     * Count dislikes for a comment
     */
    public function countDislikes(ForumComment $comment): int
    {
        return $this->count([
            'comment' => $comment,
            'type' => 'dislike',
        ]);
    }

    /**
     * Remove a user's reaction to a comment
     */
    public function removeUserReaction(User $user, ForumComment $comment): void
    {
        $reaction = $this->findUserReaction($user, $comment);
        if ($reaction) {
            $this->getEntityManager()->remove($reaction);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get all reactions for a comment
     */
    public function getReactionStats(ForumComment $comment): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.type', 'COUNT(r.id) as count')
            ->where('r.comment = :comment')
            ->setParameter('comment', $comment)
            ->groupBy('r.type');

        $results = $qb->getQuery()->getResult();

        $stats = ['like' => 0, 'dislike' => 0];
        foreach ($results as $result) {
            $stats[$result['type']] = (int)$result['count'];
        }

        return $stats;
    }
}
