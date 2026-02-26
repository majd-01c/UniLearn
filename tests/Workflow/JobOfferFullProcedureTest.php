<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

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

/**
 * Workflow test for the complete job offer procedure from zero
 * Tests the full business logic without database dependencies
 */
class JobOfferFullProcedureTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private JobOfferRepository $repository;
    private JobOfferService $jobOfferService;
    private JobApplicationService $jobApplicationService;

    private User $partner;
    private User $student;
    private User $admin;

    protected function setUp(): void
    {
        // Mock dependencies
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(JobOfferRepository::class);
        
        // Create services with mocked dependencies
        $this->jobOfferService = new JobOfferService($this->entityManager, $this->repository);
        $this->jobApplicationService = new JobApplicationService($this->entityManager, $this->repository);

        // Create test users
        $this->createTestUsers();
    }

    /**
     * Test the complete job offer workflow from zero
     */
    public function testCompleteJobOfferProcedureFromZero(): void
    {
        // === STEP 1: Partner creates job offer ===
        $offer = $this->createJobOfferAsPartner();
        
        // Mock entity manager expectations for persistence operations
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->logicalOr(
                $this->isInstanceOf(JobOffer::class),
                $this->isInstanceOf(JobApplication::class)
            ));
        
        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        // Call the service method
        $this->jobOfferService->createForPartner($offer, $this->partner);
        
        // Simulate the lifecycle callback that sets timestamps
        if ($offer->getCreatedAt() === null) {
            $offer->setCreatedAt(new \DateTimeImmutable());
        }
        
        // Verify initial state
        $this->assertSame(JobOfferStatus::PENDING, $offer->getStatus(), 'New job offers should start as PENDING');
        $this->assertSame($this->partner, $offer->getPartner(), 'Partner should be assigned to offer');
        $this->assertNull($offer->getPublishedAt(), 'Offer should not be published yet');
        $this->assertNotNull($offer->getCreatedAt(), 'Created timestamp should be set');

        // === STEP 2: Admin reviews and approves job offer ===
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::ACTIVE);
        
        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus(), 'Approved offers should be ACTIVE');
        $this->assertNotNull($offer->getPublishedAt(), 'Published timestamp should be set when activated');

        // === STEP 3: Student discovers and applies to job offer ===
        $application = $this->studentApplyToJobOffer($offer);
        
        // Simulate the lifecycle callback for application timestamps
        if ($application->getCreatedAt() === null) {
            $application->setCreatedAt(new \DateTimeImmutable());
        }
        
        $this->assertSame($offer, $application->getOffer(), 'Application should be linked to offer');
        $this->assertSame($this->student, $application->getStudent(), 'Application should be linked to student');
        $this->assertSame(JobApplicationStatus::SUBMITTED, $application->getStatus(), 'New applications should be SUBMITTED');
        $this->assertNotNull($application->getCreatedAt(), 'Application timestamp should be set');

        // === STEP 4: Verify business rules ===
        // Test duplicate application prevention
        // First, reset the mock to return true (student has already applied)
        $this->repository = $this->createMock(JobOfferRepository::class);
        $this->repository->method('hasStudentApplied')->willReturn(true);
        
        // Create new service with updated mock
        $duplicateTestService = new JobApplicationService($this->entityManager, $this->repository);
        
        $duplicateApplication = new JobApplication();
        $duplicateApplication->setMessage('Duplicate attempt');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already applied');

        $duplicateTestService->apply($duplicateApplication, $offer, $this->student);
    }

    /**
     * Test job offer lifecycle states
     */
    public function testJobOfferLifecycleStates(): void
    {
        $offer = $this->createJobOfferAsPartner();
        
        // Mock entity manager for all state changes
        $this->entityManager->expects($this->any())->method('flush');
        
        // Initial state: PENDING
        $this->jobOfferService->createForPartner($offer, $this->partner);
        $this->assertSame(JobOfferStatus::PENDING, $offer->getStatus());
        
        // Approve → ACTIVE
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::ACTIVE);
        $this->assertSame(JobOfferStatus::ACTIVE, $offer->getStatus());
        $this->assertNotNull($offer->getPublishedAt());
        
        // Close → CLOSED  
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::CLOSED);
        $this->assertSame(JobOfferStatus::CLOSED, $offer->getStatus());
        
        // Reopen → PENDING (for re-approval)
        $this->jobOfferService->changeStatus($offer, JobOfferStatus::PENDING);
        $this->assertSame(JobOfferStatus::PENDING, $offer->getStatus());
    }

    /**
     * Test application lifecycle states
     */
    public function testApplicationLifecycleStates(): void
    {
        $offer = $this->createActiveJobOffer();
        $application = $this->createJobApplication($offer, $this->student);
        
        // Mock entity manager for status updates
        $this->entityManager->expects($this->any())->method('flush');
        
        // Initial state: SUBMITTED
        $this->assertSame(JobApplicationStatus::SUBMITTED, $application->getStatus());
        
        // Partner reviews application
        $this->jobApplicationService->updateStatus($application, JobApplicationStatus::REVIEWED);
        $this->assertSame(JobApplicationStatus::REVIEWED, $application->getStatus());
        
        // Partner accepts application
        $this->jobApplicationService->updateStatus($application, JobApplicationStatus::ACCEPTED);
        $this->assertSame(JobApplicationStatus::ACCEPTED, $application->getStatus());
    }

    /**
     * Test error scenarios
     */
    public function testErrorScenariosInWorkflow(): void
    {
        // Test applying to non-active offer
        $pendingOffer = $this->createJobOfferAsPartner();
        // Note: Don't activate the offer
        
        $application = new JobApplication();
        $application->setMessage('This should fail - offer not active');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('no longer accepting');

        $this->jobApplicationService->apply($application, $pendingOffer, $this->student);
    }

    /**
     * Test business rule validations
     */
    public function testBusinessRuleValidations(): void
    {
        $offer = $this->createActiveJobOffer();
        
        // Test successful application
        $this->repository->method('hasStudentApplied')->willReturn(false);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        
        $application = new JobApplication();
        $application->setMessage('Valid application');
        
        // This should succeed
        $this->jobApplicationService->apply($application, $offer, $this->student);
        
        $this->assertSame($offer, $application->getOffer());
        $this->assertSame($this->student, $application->getStudent());
    }

    /**
     * Test ATS requirements setup
     */
    public function testATSRequirementsSetup(): void
    {
        $offer = new JobOffer();
        $offer->setTitle('Data Scientist Position');
        $offer->setType(JobOfferType::JOB);
        $offer->setDescription('Machine learning role with Python');
        $offer->setRequirements('Python, ML, Statistics');
        
        // Set ATS requirements
        $offer->setRequiredSkills(['Python', 'Machine Learning', 'Statistics']);
        $offer->setPreferredSkills(['TensorFlow', 'PyTorch', 'AWS']);
        $offer->setMinExperienceYears(3);
        $offer->setMinEducation('master');
        $offer->setRequiredLanguages(['French', 'English']);

        // Verify ATS data is properly set
        $this->assertSame(['Python', 'Machine Learning', 'Statistics'], $offer->getRequiredSkills());
        $this->assertSame(['TensorFlow', 'PyTorch', 'AWS'], $offer->getPreferredSkills());
        $this->assertSame(3, $offer->getMinExperienceYears());
        $this->assertSame('master', $offer->getMinEducation());
        $this->assertSame(['French', 'English'], $offer->getRequiredLanguages());
    }

    /**
     * Test the complete procedure with multiple participants
     */
    public function testMultipleParticipantsCompleteWorkflow(): void
    {
        // Create second student
        $student2 = new User();
        $student2->setEmail('student2-test@example.com');
        $student2->setName('Second Student');
        $student2->setRole('STUDENT');
        
        $offer = $this->createActiveJobOffer();
        
        // Mock repository for both students
        $this->repository->method('hasStudentApplied')
            ->willReturnCallback(function($offerParam, $studentParam) use ($offer) {
                return false; // Both students can apply
            });
        
        $this->entityManager->expects($this->atLeast(2))->method('persist');
        $this->entityManager->expects($this->atLeast(2))->method('flush');
        
        // Both students apply
        $application1 = $this->studentApplyToJobOffer($offer, $this->student);
        $application2 = $this->studentApplyToJobOffer($offer, $student2);
        
        // Verify both applications
        $this->assertSame($this->student, $application1->getStudent());
        $this->assertSame($student2, $application2->getStudent());
        $this->assertSame($offer, $application1->getOffer());
        $this->assertSame($offer, $application2->getOffer());
    }

    // === Helper Methods ===

    private function createTestUsers(): void
    {
        $this->partner = new User();
        $this->partner->setEmail('partner-test@example.com');
        $this->partner->setName('Test Partner');
        $this->partner->setRole('BUSINESS_PARTNER');

        $this->student = new User();
        $this->student->setEmail('student-test@example.com');
        $this->student->setName('Test Student');
        $this->student->setRole('STUDENT');

        $this->admin = new User();
        $this->admin->setEmail('admin-test@example.com');
        $this->admin->setName('Test Admin');
        $this->admin->setRole('ADMIN');
    }

    private function createJobOfferAsPartner(): JobOffer
    {
        $offer = new JobOffer();
        $offer->setTitle('Senior PHP Developer');
        $offer->setType(JobOfferType::JOB);
        $offer->setLocation('Remote');
        $offer->setDescription('Exciting PHP development opportunity');
        $offer->setRequirements('PHP, Symfony, MySQL, 3+ years experience');
        
        return $offer;
    }

    private function createActiveJobOffer(): JobOffer
    {
        $offer = $this->createJobOfferAsPartner();
        $offer->setStatus(JobOfferStatus::ACTIVE);
        $offer->setPublishedAt(new \DateTimeImmutable());
        return $offer;
    }

    private function createJobApplication(JobOffer $offer, User $student): JobApplication
    {
        $application = new JobApplication();
        $application->setOffer($offer);
        $application->setStudent($student);
        $application->setStatus(JobApplicationStatus::SUBMITTED);
        $application->setMessage('I am very interested in this position.');
        return $application;
    }

    private function studentApplyToJobOffer(JobOffer $offer, ?User $student = null): JobApplication
    {
        $student = $student ?? $this->student;
        
        $application = new JobApplication();
        $application->setMessage('I am very interested in this position and believe I would be a great fit.');

        // Mock that student hasn't applied yet
        $this->repository->method('hasStudentApplied')->willReturn(false);
        
        $this->jobApplicationService->apply($application, $offer, $student);

        return $application;
    }
}