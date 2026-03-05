<?php

namespace App\Controller\Partner;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Enum\JobApplicationStatus;
use App\Security\Voter\JobOfferVoter;
use App\Service\JobOffer\ATSScoringService;
use App\Service\JobOffer\JobApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/partner/job-application')]
#[IsGranted('ROLE_BUSINESS_PARTNER')]
class PartnerJobApplicationController extends AbstractController
{
    public function __construct(
        private readonly JobApplicationService $applicationService,
        private readonly ATSScoringService $scoringService,
    ) {
    }

    /**
     * Update job application status
     */
    #[Route('/{id}/status', name: 'app_partner_job_application_status', methods: ['POST'])]
    public function updateStatus(Request $request, JobApplication $application): Response
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
    #[Route('/{id}/calculate-score', name: 'app_partner_job_application_calculate_score', methods: ['POST'])]
    public function calculateScore(Request $request, JobApplication $application): Response
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
    #[Route('/{id}/score-details', name: 'app_partner_job_application_score_details', methods: ['GET'])]
    public function scoreDetails(JobApplication $application): Response
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
