<?php

namespace App\Controller\Student;

use App\Entity\Classe;
use App\Repository\StudentClasseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/my-classes')]
class StudentClasseController extends AbstractController
{
    public function __construct(
        private readonly StudentClasseRepository $studentClasseRepository
    ) {}

    /**
     * List all classes the current student is enrolled in
     */
    #[Route('', name: 'app_student_classes', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $user = $this->getUser();
        $enrollments = $this->studentClasseRepository->findByStudent($user);

        return $this->render('Gestion_Program/student_classe/index.html.twig', [
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * View a specific class the student is enrolled in
     */
    #[Route('/{id}', name: 'app_student_classe_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(Classe $classe): Response
    {
        $user = $this->getUser();
        
        // Check if student is enrolled in this class
        if (!$this->studentClasseRepository->isStudentEnrolled($user, $classe)) {
            $this->addFlash('error', 'You are not enrolled in this class.');
            return $this->redirectToRoute('app_student_classes');
        }

        // Get the student's enrollment details
        $enrollments = $this->studentClasseRepository->findByStudent($user);
        $enrollment = null;
        foreach ($enrollments as $e) {
            if ($e->getClasse()->getId() === $classe->getId()) {
                $enrollment = $e;
                break;
            }
        }

        return $this->render('Gestion_Program/student_classe/show.html.twig', [
            'classe' => $classe,
            'enrollment' => $enrollment,
        ]);
    }
}
