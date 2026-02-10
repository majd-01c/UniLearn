<?php

namespace App\Controller\Student;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Form\JobApplicationFormType;
use App\Repository\JobOfferRepository;
use App\Service\JobOffer\JobApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/job-offer')]
class StudentJobOfferController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly JobApplicationService $applicationService,
    ) {
    }

    /**
     * List all active job offers with search filters
     */
    #[Route('', name: 'app_job_offer_index', methods: ['GET'])]
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

        return $this->render('job_offer/index.html.twig', [
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
     * Show single job offer details
     */
    #[Route('/{id}', name: 'app_job_offer_show', methods: ['GET'])]
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

        return $this->render('job_offer/show.html.twig', [
            'offer' => $offer,
            'form' => $form?->createView(),
            'alreadyApplied' => $alreadyApplied,
        ]);
    }

    /**
     * Submit job application
     */
    #[Route('/{id}/apply', name: 'app_job_offer_apply', methods: ['POST'])]
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
}
