<?php

namespace App\Controller;

use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
        private JobOfferRepository $jobOfferRepository
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

        $queryBuilder = $this->jobOfferRepository->createQueryBuilder('o')
            ->leftJoin('o.partner', 'p')
            ->addSelect('p');

        // Filter by status
        if ($status && JobOfferStatus::tryFrom($status)) {
            $queryBuilder->andWhere('o.status = :status')
                ->setParameter('status', JobOfferStatus::from($status));
        }

        // Filter by type
        if ($type && JobOfferType::tryFrom($type)) {
            $queryBuilder->andWhere('o.type = :type')
                ->setParameter('type', JobOfferType::from($type));
        }

        // Filter by partner
        if ($partnerId) {
            $queryBuilder->andWhere('o.partner = :partner')
                ->setParameter('partner', $partnerId);
        }

        // Filter by location
        if ($location) {
            $queryBuilder->andWhere('o.location LIKE :location')
                ->setParameter('location', '%' . $location . '%');
        }

        $queryBuilder->orderBy('o.createdAt', 'DESC');

        $offers = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/job_offer/list.html.twig', [
            'offers' => $offers,
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
        // Validate CSRF token
        if ($this->isCsrfTokenValid('approve-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $offer->setStatus(JobOfferStatus::ACTIVE);
                
                // Set publishedAt to now if not already set
                if ($offer->getPublishedAt() === null) {
                    $offer->setPublishedAt(new \DateTimeImmutable());
                }
                
                $offer->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

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
        // Validate CSRF token
        if ($this->isCsrfTokenValid('reject-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $offer->setStatus(JobOfferStatus::REJECTED);
                $offer->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

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
        // Validate CSRF token
        if ($this->isCsrfTokenValid('close-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $offer->setStatus(JobOfferStatus::CLOSED);
                $offer->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

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
        // Validate CSRF token
        if ($this->isCsrfTokenValid('delete-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($offer);
                $this->entityManager->flush();

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
