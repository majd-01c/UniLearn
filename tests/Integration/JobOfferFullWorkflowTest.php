<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobOfferRepository;
use App\Service\JobOffer\ATSScoringService;
use App\Service\JobOffer\JobApplicationService;
use App\Service\JobOffer\JobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Integration test for the complete job offer workflow
 * Tests the full procedure from zero: creation → approval → application → ATS scoring
 */
class JobOfferFullWorkflowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private JobOfferService $jobOfferService;
    private JobApplicationService $jobApplicationService;
    private ATSScoringService $atsService;
    private JobOfferRepository $jobOfferRepository;

    private User $partner;
    private User $student;
    private User $admin;

    protected function setUp(): void
    {
        self::bootKernel();
        
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->jobOfferService = $container->get(JobOfferService::class);
        $this->jobApplicationService = $container->get(JobApplicationService::class);
        $this->atsService = $container->get(ATSScoringService::class);
        $this->jobOfferRepository = $container->get(JobOfferRepository::class);

        $this->createTestUsers();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\JobApplication')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\JobOffer')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User WHERE email LIKE :testEmail')
            ->setParameter('testEmail', '%test-workflow%')
            ->execute();
        
        parent::tearDown();
    }

    /**
     * Test the complete job offer workflow from zero
     */
    public function testCompleteJobOfferWorkflow(): void
    {
        // === PHASE 1: Partner creates job offer ===
        $offer = $this->createJobOfferAsPartner();
        
        $this->assertSame(JobOfferStatus::PENDING, $offer->getStatus());
        $this->assertSame($this->partner, $offer->getPartner());
        $this->assertNull($offer->getPublishedAt());
        $this->assertNotNull($offer->getCreatedAt());

        // === PHASE 2: Admin approves job offer ===
        $this->approveJobOfferAsAdmin($offer);
        
        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
        $this->assertNotNull($offer->getPublishedAt());

        // === PHASE 3: Student applies to job offer ===
        $application = $this->studentApplyToJobOffer($offer);
        
        $this->assertSame($offer, $application->getOffer());
        $this->assertSame($this->student, $application->getStudent());
        $this->assertSame(JobApplicationStatus::SUBMITTED, $application->getStatus());
        $this->assertNotNull($application->getCreatedAt());

        // === PHASE 4: Verify application is linked to offer ===
        $offerApplications = $this->jobApplicationService->getApplicationsForOffer($offer);
        $this->assertCount(1, $offerApplications);
        $this->assertSame($application, $offerApplications[0]);

        // === PHASE 5: Verify student can see their application ===
        $studentApplications = $this->jobApplicationService->getApplicationsForStudent($this->student);
        $this->assertCount(1, $studentApplications);
        $this->assertSame($application, $studentApplications[0]);

        // === PHASE 6: Partner updates application status ===
        $this->updateApplicationStatus($application, JobApplicationStatus::REVIEWED);
        
        $this->assertSame(JobApplicationStatus::REVIEWED, $application->getStatus());

        // === PHASE 7: Test duplicate application prevention ===
        $this->assertApplicationDuplicatePrevention($offer);

        // === PHASE 8: Test repository queries ===
        $this->testRepositoryQueries($offer);

        // === PHASE 9: Partner can close job offer ===
        $this->closeJobOfferAsPartner($offer);
        
        $this->assertSame(JobOfferStatus::CLOSED, $offer->getStatus());
    }

    /**
     * Test ATS scoring workflow (requires PDF file)
     * Note: This test is marked as incomplete as it requires actual PDF files
     */
    public function testATSScoringWorkflow(): void
    {
        $this->markTestIncomplete(
            'ATS scoring test requires actual PDF file and Gemini API key configuration'
        );

        // Uncomment and modify when you have test PDF files:
        /*
        $offer = $this->createJobOfferWithATSRequirements();
        $this->approveJobOfferAsAdmin($offer);
        
        $application = $this->studentApplyWithCV($offer, '/path/to/test-cv.pdf');
        
        // Test ATS scoring
        $scoreResult = $this->atsService->calculateScore($application);
        
        $this->assertIsArray($scoreResult);
        $this->assertArrayHasKey('score', $scoreResult);
        $this->assertIsInt($scoreResult['score']);
        $this->assertGreaterThanOrEqual(0, $scoreResult['score']);
        $this->assertLessThanOrEqual(100, $scoreResult['score']);
        */
    }

    // === Helper Methods ===

    private function createTestUsers(): void
    {
        // Create partner
        $this->partner = new User();
        $this->partner->setEmail('partner-test-workflow@example.com');
        $this->partner->setPassword('password');
        $this->partner->setName('Test Partner');
        $this->partner->setRole('BUSINESS_PARTNER');
        $this->partner->setIsActive(true);

        // Create student
        $this->student = new User();
        $this->student->setEmail('student-test-workflow@example.com');
        $this->student->setPassword('password');
        $this->student->setName('Test Student');
        $this->student->setRole('STUDENT');
        $this->student->setIsActive(true);

        // Create admin
        $this->admin = new User();
        $this->admin->setEmail('admin-test-workflow@example.com');
        $this->admin->setPassword('password');
        $this->admin->setName('Test Admin');
        $this->admin->setRole('ADMIN');
        $this->admin->setIsActive(true);

        $this->entityManager->persist($this->partner);
        $this->entityManager->persist($this->student);
        $this->entityManager->persist($this->admin);
        $this->entityManager->flush();
    }

    private function createJobOfferAsPartner(): JobOffer
    {
        $offer = new JobOffer();
        $offer->setTitle('Senior PHP Developer - Integration Test');
        $offer->setType(JobOfferType::JOB);
        $offer->setLocation('Remote');
        $offer->setDescription('This is a test job offer for integration testing.');
        $offer->setRequirements('PHP, Symfony, MySQL');
        
        // ATS requirements
        $offer->setRequiredSkills(['PHP', 'Symfony', 'MySQL']);
        $offer->setPreferredSkills(['Docker', 'Redis']);
        $offer->setMinExperienceYears(3);
        $offer->setMinEducation('licence');
        $offer->setRequiredLanguages(['French', 'English']);

        $this->jobOfferService->createForPartner($offer, $this->partner);

        return $offer;
    }

    private function createJobOfferWithATSRequirements(): JobOffer
    {
        $offer = new JobOffer();
        $offer->setTitle('Data Scientist - ATS Test');
        $offer->setType(JobOfferType::JOB);
        $offer->setLocation('Paris');
        $offer->setDescription('Data science position for ATS testing.');
        $offer->setRequirements('Python, Machine Learning, Statistics');
        
        // Comprehensive ATS requirements
        $offer->setRequiredSkills(['Python', 'Machine Learning', 'Statistics', 'SQL']);
        $offer->setPreferredSkills(['TensorFlow', 'PyTorch', 'AWS', 'Docker']);
        $offer->setMinExperienceYears(5);
        $offer->setMinEducation('master');
        $offer->setRequiredLanguages(['French', 'English']);

        $this->jobOfferService->createForPartner($offer, $this->partner);

        return $offer;
    }

    private function approveJobOfferAsAdmin(JobOffer $offer): void
    {
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::ACTIVE);
    }

    private function studentApplyToJobOffer(JobOffer $offer): JobApplication
    {
        $application = new JobApplication();
        $application->setMessage('I am very interested in this position. I have 5 years of experience with PHP and Symfony.');

        $this->jobApplicationService->apply($application, $offer, $this->student);

        return $application;
    }

    private function studentApplyWithCV(JobOffer $offer, string $cvPath): JobApplication
    {
        $application = new JobApplication();
        $application->setMessage('Application with CV for ATS testing.');
        
        // Note: In a real test, you would set up a test PDF file
        // $uploadedFile = new UploadedFile($cvPath, 'test-cv.pdf', 'application/pdf', null, true);
        // $application->setCvFile($uploadedFile);

        $this->jobApplicationService->apply($application, $offer, $this->student);

        return $application;
    }

    private function updateApplicationStatus(JobApplication $application, JobApplicationStatus $newStatus): void
    {
        $this->jobApplicationService->updateStatus($application, $newStatus);
    }

    private function assertApplicationDuplicatePrevention(JobOffer $offer): void
    {
        $duplicateApplication = new JobApplication();
        $duplicateApplication->setMessage('Duplicate application attempt');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already applied');

        $this->jobApplicationService->apply($duplicateApplication, $offer, $this->student);
    }

    private function testRepositoryQueries(JobOffer $offer): void
    {
        // Test has student applied
        $hasApplied = $this->jobOfferRepository->hasStudentApplied($offer, $this->student);
        $this->assertTrue($hasApplied);

        // Test count by status
        $pendingCount = $this->jobOfferRepository->countByStatus(JobOfferStatus::PENDING);
        $this->assertIsInt($pendingCount);

        // Test search pagination
        $paginator = $this->jobOfferRepository->searchPaginated(
            'Senior PHP', // query
            JobOfferType::JOB, // type
            'Remote', // location
            JobOfferStatus::ACTIVE, // status
            1, // page
            10 // limit
        );
        
        $this->assertGreaterThanOrEqual(1, count($paginator));

        // Test partner offers
        $partnerPaginator = $this->jobOfferRepository->findByPartnerPaginated($this->partner, 1, 10);
        $this->assertGreaterThanOrEqual(1, count($partnerPaginator));
    }

    private function closeJobOfferAsPartner(JobOffer $offer): void
    {
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::CLOSED);
    }

    /**
     * Test error scenarios
     */
    public function testErrorScenarios(): void
    {
        $offer = $this->createJobOfferAsPartner();
        
        // Test applying to non-active offer
        $application = new JobApplication();
        $application->setMessage('This should fail');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no longer accepting');

        $this->jobApplicationService->apply($application, $offer, $this->student);
    }

    /**
     * Test job offer lifecycle
     */
    public function testJobOfferLifecycle(): void
    {
        $offer = $this->createJobOfferAsPartner();
        
        // Initial state: PENDING
        $this->assertSame(JobOfferStatus::PENDING, $offer->getStatus());
        
        // Approve → ACTIVE
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::ACTIVE);
        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
        
        // Close → CLOSED  
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::CLOSED);
        $this->assertSame(JobOfferStatus::CLOSED, $offer->getStatus());
        
        // Reopen → PENDING (for re-approval)
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::PENDING);
        $this->assertSame(JobOfferStatus::PENDING, $offer->getStatus());
    }

    /**
     * Test application status workflow
     */
    public function testApplicationStatusWorkflow(): void
    {
        $offer = $this->createJobOfferAsPartner();
        $this->approveJobOfferAsAdmin($offer);
        
        $application = $this->studentApplyToJobOffer($offer);
        
        // Initial: SUBMITTED
        $this->assertSame(JobApplicationStatus::SUBMITTED, $application->getStatus());
        
        // Review process
        $this->updateApplicationStatus($application, JobApplicationStatus::REVIEWED);
        $this->assertSame(JobApplicationStatus::REVIEWED, $application->getStatus());
        
        // Accept
        $this->updateApplicationStatus($application, JobApplicationStatus::ACCEPTED);
        $this->assertSame(JobApplicationStatus::ACCEPTED, $application->getStatus());
    }

    /**
     * Test bulk operations
     */
    public function testBulkOperations(): void
    {
        // Create multiple offers
        $offer1 = $this->createJobOfferAsPartner();
        $offer2 = $this->createJobOfferAsPartner();
        $offer2->setTitle('Junior Developer - Integration Test #2');
        
        $this->approveJobOfferAsAdmin($offer1);
        $this->approveJobOfferAsAdmin($offer2);
        
        // Student applies to both
        $application1 = $this->studentApplyToJobOffer($offer1);
        $application2 = $this->studentApplyToJobOffer($offer2);
        
        // Verify student has multiple applications
        $studentApplications = $this->jobApplicationService->getApplicationsForStudent($this->student);
        $this->assertCount(2, $studentApplications);
        
        // Verify partner can see applications for each offer
        $offer1Applications = $this->jobApplicationService->getApplicationsForOffer($offer1);
        $offer2Applications = $this->jobApplicationService->getApplicationsForOffer($offer2);
        
        $this->assertCount(1, $offer1Applications);
        $this->assertCount(1, $offer2Applications);
    }
}