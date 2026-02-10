<?php

namespace App\Repository;

use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * Returns paginated search results
     *
     * @return Paginator<JobOffer>
     */
    public function searchPaginated(
        ?string $q,
        ?JobOfferType $type,
        ?string $location,
        ?JobOfferStatus $status = JobOfferStatus::ACTIVE,
        int $page = 1,
        int $limit = 12,
    ): Paginator {
        $qb = $this->createQueryBuilder('o');

        if ($status !== null) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if ($q !== null && $q !== '') {
            $qb->andWhere('o.title LIKE :query OR o.description LIKE :query')
                ->setParameter('query', '%' . $q . '%');
        }
        if ($type !== null) {
            $qb->andWhere('o.type = :type')->setParameter('type', $type);
        }
        if ($location !== null && $location !== '') {
            $qb->andWhere('o.location LIKE :location')
                ->setParameter('location', '%' . $location . '%');
        }

        $qb->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), fetchJoinCollection: true);
    }

    /**
     * Returns paginated admin list with partner eager-loading
     *
     * @return Paginator<JobOffer>
     */
    public function searchAdminPaginated(
        ?string $status,
        ?string $type,
        ?string $partnerId,
        ?string $location,
        int $page = 1,
        int $limit = 25,
    ): Paginator {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.partner', 'p')
            ->addSelect('p');

        if ($status && JobOfferStatus::tryFrom($status)) {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', JobOfferStatus::from($status));
        }
        if ($type && JobOfferType::tryFrom($type)) {
            $qb->andWhere('o.type = :type')
                ->setParameter('type', JobOfferType::from($type));
        }
        if ($partnerId) {
            $qb->andWhere('o.partner = :partner')
                ->setParameter('partner', $partnerId);
        }
        if ($location) {
            $qb->andWhere('o.location LIKE :location')
                ->setParameter('location', '%' . $location . '%');
        }

        $qb->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), fetchJoinCollection: true);
    }
}
