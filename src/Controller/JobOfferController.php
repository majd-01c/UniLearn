<?php

namespace App\Controller;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Enum\JobOfferStatus;
use App\Enum\JobOfferType;
use App\Form\JobApplicationType;
use App\Repository\JobOfferRepository;
use App\Repository\JobApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/job-offer')]
class JobOfferController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JobOfferRepository $jobOfferRepository,
        private JobApplicationRepository $jobApplicationRepository
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

        // Convert type string to enum if provided
        $typeEnum = null;
        if ($type && JobOfferType::tryFrom($type)) {
            $typeEnum = JobOfferType::from($type);
        }

        // Search only ACTIVE offers
        $offers = $this->jobOfferRepository->search(
            $q,
            $typeEnum,
            $location,
            JobOfferStatus::ACTIVE
        );

        return $this->render('job_offer/index.html.twig', [
            'offers' => $offers,
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
            
            // Check if student already applied to this offer
            $existingApplication = $this->jobApplicationRepository->findOneBy([
                'offer' => $offer,
                'student' => $user,
            ]);
            
            $alreadyApplied = $existingApplication !== null;

            // Create form if not already applied
            if (!$alreadyApplied) {
                $application = new JobApplication();
                $form = $this->createForm(JobApplicationType::class, $application, [
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
    public function apply(Request $request, JobOffer $offer, SluggerInterface $slugger): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Validate offer is ACTIVE
        if ($offer->getStatus() !== JobOfferStatus::ACTIVE) {
            $this->addFlash('error', 'This job offer is no longer active.');
            return $this->redirectToRoute('app_job_offer_show', ['id' => $offer->getId()]);
        }

        // Check for duplicate application
        $existingApplication = $this->jobApplicationRepository->findOneBy([
            'offer' => $offer,
            'student' => $user,
        ]);

        if ($existingApplication) {
            $this->addFlash('error', 'You have already applied to this job offer.');
            return $this->redirectToRoute('app_job_offer_show', ['id' => $offer->getId()]);
        }

        // Create and handle form
        $application = new JobApplication();
        $form = $this->createForm(JobApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle CV file upload
                $cvFile = $form->get('cvFile')->getData();
                if ($cvFile) {
                    $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $cvFile->guessExtension();

                    try {
                        $cvFile->move(
                            $this->getParameter('cv_files_directory'),
                            $newFilename
                        );
                        $application->setCvFile($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Error uploading CV file: ' . $e->getMessage());
                        return $this->redirectToRoute('app_job_offer_show', ['id' => $offer->getId()]);
                    }
                }

                // Set application data
                $application->setOffer($offer);
                $application->setStudent($user);
                $application->setStatus(\App\Enum\JobApplicationStatus::SUBMITTED);

                // Save application (createdAt is set automatically via PrePersist)
                $this->entityManager->persist($application);
                $this->entityManager->flush();

                $this->addFlash('success', 'Your application has been submitted successfully.');
                return $this->redirectToRoute('app_job_offer_show', ['id' => $offer->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error submitting application: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Please correct the errors in your application.');
        }

        return $this->redirectToRoute('app_job_offer_show', ['id' => $offer->getId()]);
    }
}

