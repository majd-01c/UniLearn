<?php

namespace App\Controller\Admin;

use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobOfferRepository;
use App\Service\JobOffer\JobOfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/job-offer')]
#[IsGranted('ROLE_ADMIN')]
class AdminJobOfferController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly JobOfferService $jobOfferService,
    ) {
    }

    /**
     * List all job offers with filters
     */
    #[Route('', name: 'admin_job_offer_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $status = $request->query->get('status');
        $type = $request->query->get('type');
        $partnerId = $request->query->get('partner');
        $location = $request->query->get('location');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $paginator = $this->jobOfferRepository->searchAdminPaginated(
            $status, $type, $partnerId, $location, $page, $limit
        );

        $totalItems = count($paginator);
        $totalPages = (int) ceil($totalItems / $limit);

        return $this->render('admin/job_offer/list.html.twig', [
            'offers' => $paginator,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'currentStatus' => $status,
            'currentType' => $type,
            'currentPartner' => $partnerId,
            'currentLocation' => $location,
            'jobOfferStatuses' => JobOfferStatus::cases(),
            'jobOfferTypes' => JobOfferType::cases(),
        ]);
    }

    /**
     * Approve job offer (set to ACTIVE)
     */
    #[Route('/{id}/approve', name: 'admin_job_offer_approve', methods: ['POST'])]
    public function approve(Request $request, JobOffer $offer): Response
    {
        if ($this->isCsrfTokenValid('approve-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->jobOfferService->changeStatus($offer, JobOfferStatus::ACTIVE);
                $this->addFlash('success', 'Job offer approved successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error approving job offer: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_job_offer_list');
    }

    /**
     * Reject job offer
     */
    #[Route('/{id}/reject', name: 'admin_job_offer_reject', methods: ['POST'])]
    public function reject(Request $request, JobOffer $offer): Response
    {
        if ($this->isCsrfTokenValid('reject-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->jobOfferService->changeStatus($offer, JobOfferStatus::REJECTED);
                $this->addFlash('success', 'Job offer rejected successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error rejecting job offer: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_job_offer_list');
    }

    /**
     * Close job offer
     */
    #[Route('/{id}/close', name: 'admin_job_offer_close', methods: ['POST'])]
    public function close(Request $request, JobOffer $offer): Response
    {
        if ($this->isCsrfTokenValid('close-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->jobOfferService->changeStatus($offer, JobOfferStatus::CLOSED);
                $this->addFlash('success', 'Job offer closed successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error closing job offer: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_job_offer_list');
    }

    /**
     * Delete job offer
     */
    #[Route('/{id}/delete', name: 'admin_job_offer_delete', methods: ['POST'])]
    public function delete(Request $request, JobOffer $offer): Response
    {
        if ($this->isCsrfTokenValid('delete-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->jobOfferService->delete($offer);
                $this->addFlash('success', 'Job offer deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting job offer: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_job_offer_list');
    }
}
