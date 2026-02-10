<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobOfferRepository;
use App\Service\JobOffer\JobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class JobOfferServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private JobOfferRepository $repo;
    private JobOfferService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(JobOfferRepository::class);
        $this->service = new JobOfferService($this->em, $this->repo);
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
        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
        $this->assertNotNull($offer->getPublishedAt());
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
}
