<?php

namespace App\Controller\Partner;

use App\Entity\JobApplication;
use App\Enum\JobApplicationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/partner/job-application')]
#[IsGranted('ROLE_BUSINESS_PARTNER')]
class PartnerJobApplicationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Update job application status
     */
    #[Route('/{id}/status', name: 'app_partner_job_application_status', methods: ['POST'])]
    public function updateStatus(Request $request, JobApplication $application): Response
    {
        // Check ownership - partner must own the offer
        $this->denyAccessUnlessOwner($application);

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
            $application->setStatus($newStatus);
            $this->entityManager->flush();

            $statusLabel = ucfirst(strtolower($newStatus->value));
            $this->addFlash('success', sprintf('Application status updated to %s successfully!', $statusLabel));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error updating application status: ' . $e->getMessage());
        }

        return $this->redirectToApplicationsList($application);
    }

    /**
     * Check if current user owns the offer associated with this application
     */
    private function denyAccessUnlessOwner(JobApplication $application): void
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($application->getOffer()->getPartner() !== $user) {
            throw $this->createNotFoundException('Application not found.');
        }
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
