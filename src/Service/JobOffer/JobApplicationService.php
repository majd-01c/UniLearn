<?php

declare(strict_types=1);

namespace App\Service\JobOffer;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Repository\JobApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JobApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JobApplicationRepository $applicationRepository,
    ) {
    }

    public function hasAlreadyApplied(JobOffer $offer, User $student): bool
    {
        return $this->applicationRepository->findOneBy([
            'offer' => $offer,
            'student' => $student,
        ]) !== null;
    }

    /**
     * @throws \LogicException if offer not active or student already applied
     */
    public function apply(
        JobApplication $application,
        JobOffer $offer,
        User $student,
    ): void {
        if ($offer->getStatus() !== JobOfferStatus::ACTIVE) {
            throw new \LogicException('This job offer is no longer accepting applications.');
        }

        if ($this->hasAlreadyApplied($offer, $student)) {
            throw new \LogicException('You have already applied to this job offer.');
        }

        $application->setOffer($offer);
        $application->setStudent($student);
        $application->setStatus(JobApplicationStatus::SUBMITTED);

        $this->em->persist($application);
        $this->em->flush();
    }

    public function updateStatus(JobApplication $application, JobApplicationStatus $status): void
    {
        $application->setStatus($status);
        $this->em->flush();
    }

    /** @return JobApplication[] */
    public function getApplicationsForOffer(JobOffer $offer): array
    {
        return $this->applicationRepository->findBy(
            ['offer' => $offer],
            ['createdAt' => 'DESC']
        );
    }
}
