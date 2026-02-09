<?php

namespace App\Controller\Partner;

use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Form\JobOfferType;
use App\Repository\JobOfferRepository;
use App\Repository\JobApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
        private JobOfferRepository $jobOfferRepository,
        private JobApplicationRepository $jobApplicationRepository
    ) {
    }

    /**
     * List all job offers for current partner
     */
    #[Route('', name: 'app_partner_job_offer_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Get only current partner's offers
        $offers = $this->jobOfferRepository->findBy(
            ['partner' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('partner/job_offer/index.html.twig', [
            'offers' => $offers,
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
        $form = $this->createForm(JobOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Set partner to current user
                $offer->setPartner($user);
                
                // Set status to ACTIVE by default
                $offer->setStatus(JobOfferStatus::ACTIVE);
                
                // Set publishedAt if not set
                if ($offer->getPublishedAt() === null) {
                    $offer->setPublishedAt(new \DateTimeImmutable());
                }

                $this->entityManager->persist($offer);
                $this->entityManager->flush();

                $this->addFlash('success', 'Job offer created successfully!');
                return $this->redirectToRoute('app_partner_job_offer_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating job offer: ' . $e->getMessage());
            }
        }

        return $this->render('partner/job_offer/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit existing job offer
     */
    #[Route('/{id}/edit', name: 'app_partner_job_offer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, JobOffer $offer): Response
    {
        // Check ownership
        $this->denyAccessUnlessOwner($offer);

        $form = $this->createForm(JobOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $offer->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->addFlash('success', 'Job offer updated successfully!');
                return $this->redirectToRoute('app_partner_job_offer_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating job offer: ' . $e->getMessage());
            }
        }

        return $this->render('partner/job_offer/edit.html.twig', [
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
        // Check ownership
        $this->denyAccessUnlessOwner($offer);

        // Validate CSRF token
        if ($this->isCsrfTokenValid('close-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $offer->setStatus(JobOfferStatus::CLOSED);
                $offer->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

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
     * Reopen job offer
     */
    #[Route('/{id}/reopen', name: 'app_partner_job_offer_reopen', methods: ['POST'])]
    public function reopen(Request $request, JobOffer $offer): Response
    {
        // Check ownership
        $this->denyAccessUnlessOwner($offer);

        // Validate CSRF token
        if ($this->isCsrfTokenValid('reopen-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $offer->setStatus(JobOfferStatus::ACTIVE);
                $offer->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->addFlash('success', 'Job offer reopened successfully!');
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
        // Check ownership
        $this->denyAccessUnlessOwner($offer);

        // Validate CSRF token
        if ($this->isCsrfTokenValid('delete-' . $offer->getId(), $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($offer);
                $this->entityManager->flush();

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
    public function applications(JobOffer $offer): Response
    {
        // Check ownership
        $this->denyAccessUnlessOwner($offer);

        // Get applications ordered by createdAt DESC
        $applications = $this->jobApplicationRepository->findBy(
            ['offer' => $offer],
            ['createdAt' => 'DESC']
        );

        return $this->render('partner/job_offer/applications.html.twig', [
            'offer' => $offer,
            'applications' => $applications,
        ]);
    }

    /**
     * Check if current user owns the job offer
     */
    private function denyAccessUnlessOwner(JobOffer $offer): void
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($offer->getPartner() !== $user) {
            throw $this->createNotFoundException('Job offer not found.');
        }
    }
}
