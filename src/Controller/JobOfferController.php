<?php

namespace App\Controller;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Enum\JobApplicationStatus;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Form\JobApplicationFormType;
use App\Form\JobOfferFormType;
use App\Repository\JobOfferRepository;
use App\Security\Voter\JobOfferVoter;
use App\Service\JobOffer\ATSScoringService;
use App\Service\JobOffer\JobApplicationService;
use App\Service\JobOffer\JobOfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Consolidated Job Offer Controller handling all job offer operations
 * for students, partners, and public access.
 */
class JobOfferController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly JobOfferService $jobOfferService,
        private readonly JobApplicationService $applicationService,
        private readonly ATSScoringService $scoringService,
    ) {
    }

    // === PUBLIC & STUDENT ROUTES ===

    /**
     * List all active job offers with search filters (public access)
     */
    #[Route('/job-offers', name: 'app_job_offer_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get query parameters
        $q = $request->query->get('q');
        $type = $request->query->get('type');
        $location = $request->query->get('location');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;

        // Convert type string to enum if provided
        $typeEnum = null;
        if ($type && JobOfferType::tryFrom($type)) {
            $typeEnum = JobOfferType::from($type);
        }

        // Search only ACTIVE offers with pagination
        $paginator = $this->jobOfferRepository->searchPaginated(
            $q, $typeEnum, $location, JobOfferStatus::ACTIVE, $page, $limit
        );

        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);

        return $this->render('Gestion_Job_Offre/job_offer/index.html.twig', [
            'offers' => $paginator,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'currentQ' => $q,
            'currentType' => $type,
            'currentLocation' => $location,
            'jobOfferTypes' => JobOfferType::cases(),
        ]);
    }

    /**
     * Show single job offer details (public access)
     */
    #[Route('/job-offers/{id}', name: 'app_job_offer_show', methods: ['GET'])]
    public function show(JobOffer $offer): Response
    {
        $form = null;
        $alreadyApplied = false;

        // If user is a student, prepare application form
        if ($this->isGranted('ROLE_STUDENT')) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();

            $alreadyApplied = $this->applicationService->hasAlreadyApplied($offer, $user);

            // Create form if not already applied
            if (!$alreadyApplied) {
                $application = new JobApplication();
                $form = $this->createForm(JobApplicationFormType::class, $application, [
                    'action' => $this->generateUrl('app_job_offer_apply', ['id' => $offer->getId()]),
                    'method' => 'POST',
                ]);
            }
        }

        return $this->render('Gestion_Job_Offre/job_offer/show.html.twig', [
            'offer' => $offer,
            'form' => $form?->createView(),
            'alreadyApplied' => $alreadyApplied,
        ]);
    }

    /**
     * Submit job application (students only)
     */
    #[Route('/job-offers/{id}/apply', name: 'app_job_offer_apply', methods: ['POST'])]
    #[IsGranted('ROLE_STUDENT')]
    public function apply(Request $request, JobOffer $offer): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $application = new JobApplication();
        $form = $this->createForm(JobApplicationFormType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->applicationService->apply($application, $offer, $user);
                $this->addFlash('success', 'Your application has been submitted successfully.');
            } catch (\LogicException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error submitting application: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Please correct the errors in your application.');
        }

        return $this->redirectToRoute('app_job_offer_show', ['id' => $offer->getId()]);
    }

    /**
     * View student's job applications with status (students only)
     */
    #[Route('/my-applications', name: 'app_student_job_applications', methods: ['GET'])]
    #[IsGranted('ROLE_STUDENT')]
    public function myApplications(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $applications = $this->applicationService->getApplicationsForStudent($user);

        // Mark unnotified applications with decisions as notified
        foreach ($applications as $application) {
            if ($application->needsStatusNotification()) {
                $this->applicationService->markStatusAsNotified($application);
            }
        }

        return $this->render('Gestion_Job_Offre/student/applications.html.twig', [
            'applications' => $applications,
        ]);
    }

    // === PARTNER ROUTES ===

    /**
     * List all job offers for current partner
     */
    #[Route('/partner/job-offers', name: 'app_partner_job_offer_index', methods: ['GET'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function partnerIndex(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $paginator = $this->jobOfferService->getPartnerOffersPaginated($user, $page, $limit);
        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);

        return $this->render('Gestion_Job_Offre/partner/job_offer/index.html.twig', [
            'offers' => $paginator,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
        ]);
    }

    /**
     * Create new job offer
     */
    #[Route('/partner/job-offers/new', name: 'app_partner_job_offer_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function new(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $offer = new JobOffer();
        $form = $this->createForm(JobOfferFormType::class, $offer, [
            'partner' => $user,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->jobOfferService->createForPartner($offer, $user);
                $this->addFlash('success', 'Job offer created successfully!');
                return $this->redirectToRoute('app_partner_job_offer_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating job offer: ' . $e->getMessage());
            }
        }

        // Return 422 when form has validation errors (required for Turbo Drive)
        $response = new Response('', $form->isSubmitted() && !$form->isValid()
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK
        );

        return $this->render('Gestion_Job_Offre/partner/job_offer/new.html.twig', [
            'form' => $form->createView(),
        ], $response);
    }

    /**
     * Edit existing job offer
     */
    #[Route('/partner/job-offers/{id}/edit', name: 'app_partner_job_offer_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function edit(Request $request, JobOffer $offer): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::EDIT, $offer);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(JobOfferFormType::class, $offer, [
            'partner' => $user,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->jobOfferService->update($offer);
                $this->addFlash('success', 'Job offer updated successfully!');
                return $this->redirectToRoute('app_partner_job_offer_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating job offer: ' . $e->getMessage());
            }
        }

        // Return 422 when form has validation errors (required for Turbo Drive)
        $response = new Response('', $form->isSubmitted() && !$form->isValid()
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK
        );

        return $this->render('Gestion_Job_Offre/partner/job_offer/edit.html.twig', [
            'form' => $form->createView(),
            'offer' => $offer,
        ], $response);
    }

    /**
     * Close job offer
     */
    #[Route('/partner/job-offers/{id}/close', name: 'app_partner_job_offer_close', methods: ['POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function close(Request $request, JobOffer $offer): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::CLOSE, $offer);

        if ($this->isCsrfTokenValid('close-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->jobOfferService->changeStatus($offer, JobOfferStatus::CLOSED);
                $this->addFlash('success', 'Job offer closed successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error closing job offer: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_partner_job_offer_index');
    }

    /**
     * Reopen a closed job offer (sets back to PENDING for admin re-approval)
     */
    #[Route('/partner/job-offers/{id}/reopen', name: 'app_partner_job_offer_reopen', methods: ['POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function reopen(Request $request, JobOffer $offer): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::REOPEN, $offer);

        if ($this->isCsrfTokenValid('reopen-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->jobOfferService->changeStatus($offer, JobOfferStatus::PENDING);
                $this->addFlash('success', 'Job offer reopened and sent for admin approval!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error reopening job offer: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_partner_job_offer_index');
    }

    /**
     * Delete job offer
     */
    #[Route('/partner/job-offers/{id}/delete', name: 'app_partner_job_offer_delete', methods: ['POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function delete(Request $request, JobOffer $offer): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::DELETE, $offer);

        if ($this->isCsrfTokenValid('delete-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->jobOfferService->delete($offer);
                $this->addFlash('success', 'Job offer deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting job offer: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_partner_job_offer_index');
    }

    /**
     * View applications for a job offer
     */
    #[Route('/partner/job-offers/{id}/applications', name: 'app_partner_job_offer_applications', methods: ['GET'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function applications(Request $request, JobOffer $offer): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::VIEW_APPLICATIONS, $offer);

        $applications = $this->applicationService->getApplicationsForOffer($offer);
        
        // Sort by score (descending) if sort parameter is set
        $sort = $request->query->get('sort', 'score');
        if ($sort === 'score') {
            usort($applications, function($a, $b) {
                $scoreA = $a->getScore() ?? -1;
                $scoreB = $b->getScore() ?? -1;
                return $scoreB <=> $scoreA; // Descending
            });
        }

        return $this->render('Gestion_Job_Offre/partner/job_offer/applications.html.twig', [
            'offer' => $offer,
            'applications' => $applications,
            'currentSort' => $sort,
        ]);
    }

    /**
     * Calculate ATS scores for all applications of an offer
     */
    #[Route('/partner/job-offers/{id}/calculate-all-scores', name: 'app_partner_job_offer_calculate_all_scores', methods: ['POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function calculateAllScores(Request $request, JobOffer $offer): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::VIEW_APPLICATIONS, $offer);

        if (!$this->isCsrfTokenValid('calculate-all-' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_partner_job_offer_applications', ['id' => $offer->getId()]);
        }

        try {
            $results = $this->scoringService->calculateScoresForOffer($offer);
            $count = count($results);
            $this->addFlash('success', sprintf(
                'Scores ATS calculés pour %d candidature(s).',
                $count
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du calcul des scores: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_partner_job_offer_applications', ['id' => $offer->getId()]);
    }

    // === JOB APPLICATION MANAGEMENT ROUTES ===

    /**
     * Update job application status
     */
    #[Route('/partner/job-applications/{id}/status', name: 'app_partner_job_application_status', methods: ['POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function updateApplicationStatus(Request $request, JobApplication $application): Response
    {
        // Check ownership - partner must own the offer
        $this->denyAccessUnlessGranted(JobOfferVoter::VIEW_APPLICATIONS, $application->getOffer());

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('status-' . $application->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToApplicationsList($application);
        }

        // Read status from request
        $statusValue = $request->request->get('status');

        // Validate it's a valid JobApplicationStatus enum value
        try {
            $newStatus = JobApplicationStatus::from($statusValue);
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Invalid application status provided.');
            return $this->redirectToApplicationsList($application);
        }

        // Update application status
        try {
            $this->applicationService->updateStatus($application, $newStatus);
            $statusLabel = ucfirst(strtolower($newStatus->value));
            $this->addFlash('success', sprintf('Application status updated to %s successfully!', $statusLabel));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error updating application status: ' . $e->getMessage());
        }

        return $this->redirectToApplicationsList($application);
    }

    /**
     * Calculate ATS score for a single application
     */
    #[Route('/partner/job-applications/{id}/calculate-score', name: 'app_partner_job_application_calculate_score', methods: ['POST'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function calculateApplicationScore(Request $request, JobApplication $application): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::VIEW_APPLICATIONS, $application->getOffer());

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('score-' . $application->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToApplicationsList($application);
        }

        try {
            $result = $this->scoringService->calculateScore($application);
            $this->addFlash('success', sprintf(
                'Score ATS calculé: %d/100',
                $result['score']
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du calcul du score: ' . $e->getMessage());
        }

        return $this->redirectToApplicationsList($application);
    }

    /**
     * View score details for an application
     */
    #[Route('/partner/job-applications/{id}/score-details', name: 'app_partner_job_application_score_details', methods: ['GET'])]
    #[IsGranted('ROLE_BUSINESS_PARTNER')]
    public function applicationScoreDetails(JobApplication $application): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::VIEW_APPLICATIONS, $application->getOffer());

        return $this->render('Gestion_Job_Offre/partner/job_offer/score_details.html.twig', [
            'application' => $application,
            'breakdown' => $application->getScoreBreakdown(),
            'extractedData' => $application->getExtractedData(),
        ]);
    }

    /**
     * Redirect to the applications list for the offer
     */
    private function redirectToApplicationsList(JobApplication $application): Response
    {
        return $this->redirectToRoute('app_partner_job_offer_applications', [
            'id' => $application->getOffer()->getId(),
        ]);
    }
}