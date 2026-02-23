<?php

namespace App\Controller\Partner;

use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Form\JobOfferFormType;
use App\Security\Voter\JobOfferVoter;
use App\Service\JobOffer\ATSScoringService;
use App\Service\JobOffer\JobOfferService;
use App\Service\JobOffer\JobApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/partner/job-offer')]
#[IsGranted('ROLE_BUSINESS_PARTNER')]
class PartnerJobOfferController extends AbstractController
{
    public function __construct(
        private readonly JobOfferService $jobOfferService,
        private readonly JobApplicationService $applicationService,
        private readonly ATSScoringService $scoringService,
    ) {
    }

    /**
     * List all job offers for current partner
     */
    #[Route('', name: 'app_partner_job_offer_index', methods: ['GET'])]
    public function index(Request $request): Response
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
    #[Route('/new', name: 'app_partner_job_offer_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $offer = new JobOffer();
        $form = $this->createForm(JobOfferFormType::class, $offer);
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

        return $this->render('Gestion_Job_Offre/partner/job_offer/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit existing job offer
     */
    #[Route('/{id}/edit', name: 'app_partner_job_offer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, JobOffer $offer): Response
    {
        $this->denyAccessUnlessGranted(JobOfferVoter::EDIT, $offer);

        $form = $this->createForm(JobOfferFormType::class, $offer);
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

        return $this->render('Gestion_Job_Offre/partner/job_offer/edit.html.twig', [
            'form' => $form->createView(),
            'offer' => $offer,
        ]);
    }

    /**
     * Close job offer
     */
    #[Route('/{id}/close', name: 'app_partner_job_offer_close', methods: ['POST'])]
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
    #[Route('/{id}/reopen', name: 'app_partner_job_offer_reopen', methods: ['POST'])]
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
    #[Route('/{id}/delete', name: 'app_partner_job_offer_delete', methods: ['POST'])]
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
    #[Route('/{id}/applications', name: 'app_partner_job_offer_applications', methods: ['GET'])]
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
    #[Route('/{id}/calculate-all-scores', name: 'app_partner_job_offer_calculate_all_scores', methods: ['POST'])]
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

}
