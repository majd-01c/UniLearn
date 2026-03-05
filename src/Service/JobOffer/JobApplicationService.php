<?php

declare(strict_types=1);

namespace App\Service\JobOffer;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JobApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly JobOfferRepository $jobOfferRepository,
    ) {
    }

    public function hasAlreadyApplied(JobOffer $offer, User $student): bool
    {
        return $this->jobOfferRepository->hasStudentApplied($offer, $student);
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
        $oldStatus = $application->getStatus();
        $application->setStatus($status);
        
        // If status changed to ACCEPTED or REJECTED, mark for notification
        if ($oldStatus !== $status && in_array($status, [JobApplicationStatus::ACCEPTED, JobApplicationStatus::REJECTED], true)) {
            $application->setStatusNotified(false);
            $application->setStatusNotifiedAt(null);
            
            // Set appropriate status message
            $statusMessage = match($status) {
                JobApplicationStatus::ACCEPTED => 'Congratulations! Your application has been accepted.',
                JobApplicationStatus::REJECTED => 'Thank you for your interest. Unfortunately, your application was not selected for this position.',
                default => null
            };
            $application->setStatusMessage($statusMessage);
        }
        
        $this->em->flush();
    }

    /**
     * Mark application status as notified (when student views the notification)
     */
    public function markStatusAsNotified(JobApplication $application): void
    {
        $application->setStatusNotified(true);
        $application->setStatusNotifiedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    /** @return JobApplication[] */
    public function getApplicationsForOffer(JobOffer $offer): array
    {
        return $this->em->getRepository(JobApplication::class)->findBy(
            ['offer' => $offer],
            ['createdAt' => 'DESC']
        );
    }

    /** @return JobApplication[] */
    public function getApplicationsForStudent(User $student): array
    {
        return $this->em->getRepository(JobApplication::class)->findBy(
            ['student' => $student],
            ['createdAt' => 'DESC']
        );
    }
}
