<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobOfferRepository;
use App\Service\JobOffer\JobApplicationService;
use App\Service\JobOffer\JobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class JobOfferServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private JobOfferRepository $repo;
    private JobOfferService $service;
    private JobApplicationService $applicationService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(JobOfferRepository::class);
        $this->service = new JobOfferService($this->em, $this->repo);
        $this->applicationService = new JobApplicationService($this->em, $this->repo);
    }

    public function testCreateForPartnerSetsStatusPartnerAndPublishedAt(): void
    {
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $offer = new JobOffer();
        $offer->setTitle('Test Offer');
        $offer->setDescription('Description');
        $offer->setType(JobOfferType::INTERNSHIP);

        $partner = new User();
        $partner->setRole('BUSINESS_PARTNER');

        $this->service->createForPartner($offer, $partner);

        $this->assertSame($partner, $offer->getPartner());
        $this->assertSame(JobOfferStatus::PENDING, $offer->getStatus());
        $this->assertNull($offer->getPublishedAt()); // Published only when approved
    }

    public function testChangeStatusToActive(): void
    {
        $this->em->expects($this->once())->method('flush');

        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::PENDING);

        $this->service->changeStatus($offer, JobOfferStatus::ACTIVE);

        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
        $this->assertNotNull($offer->getPublishedAt());
    }

    public function testChangeStatusToClosed(): void
    {
        $this->em->expects($this->once())->method('flush');

        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);

        $this->service->changeStatus($offer, JobOfferStatus::CLOSED);

        $this->assertSame(JobOfferStatus::CLOSED, $offer->getStatus());
    }

    public function testDelete(): void
    {
        $offer = new JobOffer();

        $this->em->expects($this->once())->method('remove')->with($offer);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($offer);
    }

    // === JobApplication Service Tests ===

    public function testJobApplicationApplySuccess(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);
        $offer->setType(JobOfferType::JOB);
        $offer->setTitle('Test');
        $offer->setDescription('Desc');

        $student = new User();
        $student->setRole('STUDENT');

        $this->repo->method('hasStudentApplied')->willReturn(false);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $application = new JobApplication();
        $this->applicationService->apply($application, $offer, $student);

        $this->assertSame($offer, $application->getOffer());
        $this->assertSame($student, $application->getStudent());
        $this->assertSame(JobApplicationStatus::SUBMITTED, $application->getStatus());
    }

    public function testJobApplicationApplyThrowsWhenOfferNotActive(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::CLOSED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no longer accepting');

        $this->applicationService->apply(new JobApplication(), $offer, new User());
    }

    public function testJobApplicationApplyThrowsOnDuplicate(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);

        $this->repo->method('hasStudentApplied')->willReturn(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already applied');

        $this->applicationService->apply(new JobApplication(), $offer, new User());
    }
}
