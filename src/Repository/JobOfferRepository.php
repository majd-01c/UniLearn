<?php

namespace App\Repository;

use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobOffer>
 */
class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    /**
     * Search job offers with filters
     *
     * @param string|null $q Search query (title or description)
     * @param JobOfferType|null $type Job offer type filter
     * @param string|null $location Location filter
     * @param JobOfferStatus|null $status Status filter (default: ACTIVE)
     * @return array<JobOffer>
     */
    public function search(
        ?string $q,
        ?JobOfferType $type,
        ?string $location,
        ?JobOfferStatus $status = JobOfferStatus::ACTIVE
    ): array {
        $qb = $this->createQueryBuilder('o');

        // Filter by status
        if ($status !== null) {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        // Search in title or description
        if ($q !== null && $q !== '') {
            $qb->andWhere('o.title LIKE :query OR o.description LIKE :query')
                ->setParameter('query', '%' . $q . '%');
        }

        // Filter by type
        if ($type !== null) {
            $qb->andWhere('o.type = :type')
                ->setParameter('type', $type);
        }

        // Filter by location
        if ($location !== null && $location !== '') {
            $qb->andWhere('o.location LIKE :location')
                ->setParameter('location', '%' . $location . '%');
        }

        // Sort by publishedAt DESC NULLS LAST, then createdAt DESC
        $qb->addSelect('CASE WHEN o.publishedAt IS NULL THEN 1 ELSE 0 END AS HIDDEN nullOrder')
            ->addOrderBy('nullOrder', 'ASC')
            ->addOrderBy('o.publishedAt', 'DESC')
            ->addOrderBy('o.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
