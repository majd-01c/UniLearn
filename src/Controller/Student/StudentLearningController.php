<?php

namespace App\Controller\Student;

use App\Entity\ClasseContenu;
use App\Entity\ClasseCourse;
use App\Entity\ClasseModule;
use App\Entity\StudentClasse;
use App\Entity\User;
use App\Repository\StudentClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student/learn')]
#[IsGranted('ROLE_STUDENT')]
class StudentLearningController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentClasseRepository $studentClasseRepository
    ) {}

    #[Route('', name: 'app_student_learning_index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Get all classes the student is enrolled in
        $enrollments = $this->studentClasseRepository->findBy([
            'student' => $user,
            'isActive' => true
        ]);

        return $this->render('Gestion_Program/student_learning/index.html.twig', [
            'enrollments' => $enrollments,
        ]);
    }

    #[Route('/classe/{id}', name: 'app_student_classe_view', requirements: ['id' => '\d+'])]
    public function viewClasse(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verify student is enrolled in this class
        $enrollment = $this->studentClasseRepository->findOneBy([
            'student' => $user,
            'classe' => $id,
            'isActive' => true
        ]);

        if (!$enrollment) {
            $this->addFlash('error', 'You are not enrolled in this class.');
            return $this->redirectToRoute('app_student_learning_index');
        }

        $classe = $enrollment->getClasse();

        return $this->render('Gestion_Program/student_learning/classe.html.twig', [
            'classe' => $classe,
            'enrollment' => $enrollment,
        ]);
    }

    #[Route('/classe/{classeId}/module/{moduleId}', name: 'app_student_module_view', requirements: ['classeId' => '\d+', 'moduleId' => '\d+'])]
    public function viewModule(int $classeId, int $moduleId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verify enrollment
        $enrollment = $this->studentClasseRepository->findOneBy([
            'student' => $user,
            'classe' => $classeId,
            'isActive' => true
        ]);

        if (!$enrollment) {
            $this->addFlash('error', 'You are not enrolled in this class.');
            return $this->redirectToRoute('app_student_learning_index');
        }

        $classe = $enrollment->getClasse();

        // Find the ClasseModule
        $classeModule = $this->entityManager->getRepository(ClasseModule::class)->find($moduleId);
        
        if (!$classeModule || $classeModule->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Module not found.');
            return $this->redirectToRoute('app_student_classe_view', ['id' => $classeId]);
        }

        // Get visible courses only
        $visibleCourses = [];
        foreach ($classeModule->getCourses() as $classeCourse) {
            if (!$classeCourse->isHidden()) {
                $visibleCourses[] = $classeCourse;
            }
        }

        return $this->render('Gestion_Program/student_learning/module.html.twig', [
            'classe' => $classe,
            'classeModule' => $classeModule,
            'visibleCourses' => $visibleCourses,
        ]);
    }

    #[Route('/classe/{classeId}/course/{courseId}', name: 'app_student_course_view', requirements: ['classeId' => '\d+', 'courseId' => '\d+'])]
    public function viewCourse(int $classeId, int $courseId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verify enrollment
        $enrollment = $this->studentClasseRepository->findOneBy([
            'student' => $user,
            'classe' => $classeId,
            'isActive' => true
        ]);

        if (!$enrollment) {
            $this->addFlash('error', 'You are not enrolled in this class.');
            return $this->redirectToRoute('app_student_learning_index');
        }

        $classe = $enrollment->getClasse();

        // Find the ClasseCourse
        $classeCourse = $this->entityManager->getRepository(ClasseCourse::class)->find($courseId);
        
        if (!$classeCourse || $classeCourse->getClasseModule()->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Course not found.');
            return $this->redirectToRoute('app_student_classe_view', ['id' => $classeId]);
        }

        // Check if course is hidden
        if ($classeCourse->isHidden()) {
            $this->addFlash('error', 'This course is not available yet.');
            return $this->redirectToRoute('app_student_module_view', [
                'classeId' => $classeId,
                'moduleId' => $classeCourse->getClasseModule()->getId()
            ]);
        }

        // Get visible contenus only
        $visibleContenus = [];
        foreach ($classeCourse->getContenus() as $classeContenu) {
            if (!$classeContenu->isHidden()) {
                $visibleContenus[] = $classeContenu;
            }
        }

        return $this->render('Gestion_Program/student_learning/course.html.twig', [
            'classe' => $classe,
            'classeCourse' => $classeCourse,
            'classeModule' => $classeCourse->getClasseModule(),
            'visibleContenus' => $visibleContenus,
        ]);
    }

    #[Route('/classe/{classeId}/contenu/{contenuId}', name: 'app_student_contenu_view', requirements: ['classeId' => '\d+', 'contenuId' => '\d+'])]
    public function viewContenu(int $classeId, int $contenuId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verify enrollment
        $enrollment = $this->studentClasseRepository->findOneBy([
            'student' => $user,
            'classe' => $classeId,
            'isActive' => true
        ]);

        if (!$enrollment) {
            $this->addFlash('error', 'You are not enrolled in this class.');
            return $this->redirectToRoute('app_student_learning_index');
        }

        $classe = $enrollment->getClasse();

        // Find the ClasseContenu
        $classeContenu = $this->entityManager->getRepository(ClasseContenu::class)->find($contenuId);
        
        if (!$classeContenu) {
            $this->addFlash('error', 'Content not found.');
            return $this->redirectToRoute('app_student_classe_view', ['id' => $classeId]);
        }

        $classeCourse = $classeContenu->getClasseCourse();
        $classeModule = $classeCourse->getClasseModule();

        // Verify content belongs to this class
        if ($classeModule->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Content not found.');
            return $this->redirectToRoute('app_student_classe_view', ['id' => $classeId]);
        }

        // Check if course or content is hidden
        if ($classeCourse->isHidden() || $classeContenu->isHidden()) {
            $this->addFlash('error', 'This content is not available yet.');
            return $this->redirectToRoute('app_student_course_view', [
                'classeId' => $classeId,
                'courseId' => $classeCourse->getId()
            ]);
        }

        // Get next and previous contenus for navigation
        $visibleContenus = [];
        foreach ($classeCourse->getContenus() as $cc) {
            if (!$cc->isHidden()) {
                $visibleContenus[] = $cc;
            }
        }

        $currentIndex = null;
        foreach ($visibleContenus as $index => $cc) {
            if ($cc->getId() === $classeContenu->getId()) {
                $currentIndex = $index;
                break;
            }
        }

        $prevContenu = $currentIndex > 0 ? $visibleContenus[$currentIndex - 1] : null;
        $nextContenu = $currentIndex < count($visibleContenus) - 1 ? $visibleContenus[$currentIndex + 1] : null;

        return $this->render('Gestion_Program/student_learning/contenu.html.twig', [
            'classe' => $classe,
            'classeModule' => $classeModule,
            'classeCourse' => $classeCourse,
            'classeContenu' => $classeContenu,
            'contenu' => $classeContenu->getContenu(),
            'prevContenu' => $prevContenu,
            'nextContenu' => $nextContenu,
            'currentIndex' => $currentIndex + 1,
            'totalContenus' => count($visibleContenus),
        ]);
    }
}
