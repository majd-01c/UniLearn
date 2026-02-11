<?php

declare(strict_types=1);

namespace App\Service\JobOffer;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobOfferStatus;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class JobOfferService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JobOfferRepository $jobOfferRepository,
    ) {
    }

    public function createForPartner(JobOffer $offer, User $partner): void
    {
        $offer->setPartner($partner);
        $offer->setStatus(JobOfferStatus::ACTIVE);

        if ($offer->getPublishedAt() === null) {
            $offer->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->persist($offer);
        $this->em->flush();
    }

    public function update(JobOffer $offer): void
    {
        // updatedAt is set by PreUpdate lifecycle callback
        $this->em->flush();
    }

    public function changeStatus(JobOffer $offer, JobOfferStatus $newStatus): void
    {
        $offer->setStatus($newStatus);

        if ($newStatus === JobOfferStatus::ACTIVE && $offer->getPublishedAt() === null) {
            $offer->setPublishedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    public function delete(JobOffer $offer): void
    {
        $this->em->remove($offer);
        $this->em->flush();
    }

    /**
     * @return Paginator<JobOffer>
     */
    public function getPartnerOffersPaginated(User $partner, int $page = 1, int $limit = 20): Paginator
    {
        return $this->jobOfferRepository->findByPartnerPaginated($partner, $page, $limit);
    }
}
