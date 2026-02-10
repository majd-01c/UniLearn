<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobApplicationRepository;
use App\Service\JobOffer\CvUploadService;
use App\Service\JobOffer\JobApplicationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class JobApplicationServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private JobApplicationRepository $repo;
    private JobApplicationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(JobApplicationRepository::class);
        // CvUploadService is final, so instantiate with real dependencies (won't be called in these tests)
        $cvService = new CvUploadService(new AsciiSlugger(), sys_get_temp_dir());
        $this->service = new JobApplicationService($this->em, $this->repo, $cvService);
    }

    public function testApplySuccess(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);
        $offer->setType(JobOfferType::JOB);
        $offer->setTitle('Test');
        $offer->setDescription('Desc');

        $student = new User();
        $student->setRole('STUDENT');

        $this->repo->method('findOneBy')->willReturn(null);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $application = new JobApplication();
        $this->service->apply($application, $offer, $student);

        $this->assertSame($offer, $application->getOffer());
        $this->assertSame($student, $application->getStudent());
        $this->assertSame(JobApplicationStatus::SUBMITTED, $application->getStatus());
    }

    public function testApplyThrowsWhenOfferNotActive(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::CLOSED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no longer accepting');

        $this->service->apply(new JobApplication(), $offer, new User());
    }

    public function testApplyThrowsOnDuplicate(): void
    {
        $offer = new JobOffer();
        $offer->setStatus(JobOfferStatus::ACTIVE);

        $this->repo->method('findOneBy')->willReturn(new JobApplication());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already applied');

        $this->service->apply(new JobApplication(), $offer, new User());
    }
}
