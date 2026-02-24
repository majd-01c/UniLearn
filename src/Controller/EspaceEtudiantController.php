<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\DocumentRequest;
use App\Form\ReclamationType;
use App\Form\DocumentRequestType;
use App\Repository\GradeRepository;
use App\Repository\ReclamationRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\ScheduleRepository;
use App\Repository\StudentClasseRepository;
use App\Service\AIRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/espace-etudiant')]
#[IsGranted('ROLE_STUDENT')]
class EspaceEtudiantController extends AbstractController
{
    #[Route('/', name: 'app_espace_etudiant_dashboard')]
    public function dashboard(
        GradeRepository $gradeRepository,
        AIRecommendationService $aiService
    ): Response {
        $user = $this->getUser();
        
        // Get recent grades
        $recentGrades = $gradeRepository->createQueryBuilder('g')
            ->where('g.student = :student')
            ->setParameter('student', $user)
            ->orderBy('g.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Get AI-powered semester results and recommendations
        $semesterResults = $aiService->calculateSemesterResults($user);
        $recommendations = $aiService->generateRecommendations($user);
        
        return $this->render('Gestion_Evaluation/espace_etudiant/dashboard.html.twig', [
            'recentGrades' => $recentGrades,
            'semesterResults' => $semesterResults,
            'recommendations' => $recommendations,
        ]);
    }

    #[Route('/notes', name: 'app_espace_etudiant_grades')]
    public function grades(GradeRepository $gradeRepository): Response
    {
        $user = $this->getUser();
        
        $grades = $gradeRepository->createQueryBuilder('g')
            ->join('g.assessment', 'a')
            ->join('a.course', 'c')
            ->where('g.student = :student')
            ->setParameter('student', $user)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('Gestion_Evaluation/espace_etudiant/grades.html.twig', [
            'grades' => $grades,
        ]);
    }

    #[Route('/resultats', name: 'app_espace_etudiant_results')]
    public function results(AIRecommendationService $aiService): Response
    {
        $user = $this->getUser();
        $results = $aiService->calculateSemesterResults($user);
        
        return $this->render('Gestion_Evaluation/espace_etudiant/results.html.twig', [
            'results' => $results,
        ]);
    }

    #[Route('/recommandations', name: 'app_espace_etudiant_recommendations')]
    public function recommendations(AIRecommendationService $aiService): Response
    {
        $user = $this->getUser();
        $recommendations = $aiService->generateRecommendations($user);
        
        return $this->render('Gestion_Evaluation/espace_etudiant/recommendations.html.twig', [
            'recommendations' => $recommendations,
        ]);
    }

   

   

    #[Route('/emploi-du-temps', name: 'app_espace_etudiant_schedule')]
    public function schedule(
        ScheduleRepository $scheduleRepository,
        StudentClasseRepository $studentClasseRepository
    ): Response {
        $user = $this->getUser();
        
        // Get student's classes using the repository
        $studentClasses = $studentClasseRepository->findBy([
            'student' => $user,
            'isActive' => true
        ]);
        
        if (empty($studentClasses)) {
            $this->addFlash('warning', 'Vous n\'êtes pas inscrit à une classe actuellement.');
            return $this->redirectToRoute('app_espace_etudiant_dashboard');
        }
        
        // Get the first active class
        $activeClass = $studentClasses[0]->getClasse();
        
        // Get schedules for the class
        $schedules = $scheduleRepository->findBy(['classe' => $activeClass]);
        
        // Organize schedules by day
        $weekSchedule = [
            'monday' => [],
            'tuesday' => [],
            'wednesday' => [],
            'thursday' => [],
            'friday' => [],
            'saturday' => [],
        ];
        
        foreach ($schedules as $schedule) {
            $day = strtolower($schedule->getDayOfWeek());
            if (isset($weekSchedule[$day])) {
                $weekSchedule[$day][] = $schedule;
            }
        }
        
        // Sort each day's schedule by time
        foreach ($weekSchedule as $day => &$daySchedules) {
            usort($daySchedules, function($a, $b) {
                return $a->getStartTime() <=> $b->getStartTime();
            });
        }
        
        return $this->render('Gestion_Evaluation/espace_etudiant/schedule.html.twig', [
            'weekSchedule' => $weekSchedule,
            'classe' => $activeClass,
        ]);
    }


    #[Route('/reclamations', name: 'app_espace_etudiant_reclamations')]
    public function reclamations(ReclamationRepository $reclamationRepository): Response
    {
        $user = $this->getUser();
        
        $reclamations = $reclamationRepository->findByStudent($user);
        
        return $this->render('Gestion_Evaluation/espace_etudiant/reclamations.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/reclamations/nouvelle', name: 'app_espace_etudiant_reclamation_new')]
    public function newReclamation(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reclamation = new Reclamation();
        $reclamation->setStudent($this->getUser());
        
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reclamation);
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre réclamation a été soumise avec succès.');
            
            return $this->redirectToRoute('app_espace_etudiant_reclamations');
        }
        
        return $this->render('Gestion_Evaluation/espace_etudiant/reclamation_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/documents', name: 'app_espace_etudiant_documents')]
    public function documents(DocumentRequestRepository $documentRequestRepository): Response
    {
        $user = $this->getUser();
        
        $documentRequests = $documentRequestRepository->findByStudent($user);
        
        return $this->render('Gestion_Evaluation/espace_etudiant/documents.html.twig', [
            'documentRequests' => $documentRequests,
        ]);
    }

    #[Route('/documents/demande', name: 'app_espace_etudiant_document_request')]
    public function requestDocument(Request $request, EntityManagerInterface $entityManager): Response
    {
        $documentRequest = new DocumentRequest();
        $documentRequest->setStudent($this->getUser());
        
        $form = $this->createForm(DocumentRequestType::class, $documentRequest);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($documentRequest);
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre demande de document a été enregistrée.');
            
            return $this->redirectToRoute('app_espace_etudiant_documents');
        }
        
        return $this->render('Gestion_Evaluation/espace_etudiant/document_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}