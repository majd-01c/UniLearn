<?php

namespace App\Repository;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobOffer>
 * 
 * Repository for JobOffer entities with JobApplication query methods
 */
class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
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

    /**
     * Paginated list of offers for a specific partner
     *
     * @return Paginator<JobOffer>
     */
    public function findByPartnerPaginated(
        User $partner,
        int $page = 1,
        int $limit = 20,
    ): Paginator {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.partner = :partner')
            ->setParameter('partner', $partner)
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($qb->getQuery(), fetchJoinCollection: true);
    }

    /**
     * Count job offers by status
     */
    public function countByStatus(JobOfferStatus $status): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // === JobApplication Methods ===

    /**
     * Check if a student has already applied to a specific job offer
     */
    public function hasStudentApplied(JobOffer $offer, User $student): bool
    {
        return (bool) $this->getEntityManager()
            ->getRepository(JobApplication::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.offer = :offer')
            ->andWhere('a.student = :student')
            ->setParameter('offer', $offer)
            ->setParameter('student', $student)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
