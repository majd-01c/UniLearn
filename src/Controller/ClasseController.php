<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Entity\StudentClasse;
use App\Entity\User;
use App\Enum\ClasseStatus;
use App\Form\ClasseType;
use App\Repository\ClasseRepository;
use App\Repository\StudentClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/classe')]
class ClasseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClasseRepository $classeRepository,
        private StudentClasseRepository $studentClasseRepository
    ) {}

    #[Route('', name: 'app_classe')]
    public function index(): Response
    {
        $classes = $this->classeRepository->findAll();
        
        // Group by program for better display
        $classesByProgram = [];
        foreach ($classes as $classe) {
            $programName = $classe->getProgram()?->getName() ?? 'No Program';
            $classesByProgram[$programName][] = $classe;
        }
        
        return $this->render('Gestion_Program/classe/index.html.twig', [
            'classes' => $classes,
            'classesByProgram' => $classesByProgram,
        ]);
    }

    #[Route('/new', name: 'app_classe_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $classe = new Classe();
        $form = $this->createForm(ClasseType::class, $classe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($classe);
            $this->entityManager->flush();

            $this->addFlash('success', 'Class created successfully!');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        return $this->render('Gestion_Program/classe/new.html.twig', [
            'classe' => $classe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_classe_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Classe $classe): Response
    {
        // Get enrolled students
        $enrolledStudents = $this->studentClasseRepository->findByClasse($classe);
        
        // Get available students (not enrolled in this class)
        $enrolledStudentIds = array_map(
            fn(StudentClasse $sc) => $sc->getStudent()->getId(),
            $enrolledStudents
        );
        
        $allStudents = $this->entityManager->getRepository(User::class)->findAll();
        $availableStudents = array_filter(
            $allStudents,
            fn(User $user) => !in_array($user->getId(), $enrolledStudentIds)
        );

        return $this->render('Gestion_Program/classe/show.html.twig', [
            'classe' => $classe,
            'enrolledStudents' => $enrolledStudents,
            'availableStudents' => $availableStudents,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_classe_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Classe $classe): Response
    {
        $form = $this->createForm(ClasseType::class, $classe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-update status based on capacity
            if ($classe->isFull() && $classe->getStatus() !== ClasseStatus::FULL) {
                $classe->setStatus(ClasseStatus::FULL);
            } elseif (!$classe->isFull() && $classe->getStatus() === ClasseStatus::FULL) {
                $classe->setStatus(ClasseStatus::ACTIVE);
            }
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Class updated successfully!');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        return $this->render('Gestion_Program/classe/edit.html.twig', [
            'classe' => $classe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_classe_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Classe $classe): Response
    {
        if ($this->isCsrfTokenValid('delete'.$classe->getId(), $request->request->get('_token'))) {
            // Remove all student enrollments first
            foreach ($classe->getStudents() as $studentClasse) {
                $this->entityManager->remove($studentClasse);
            }
            
            $this->entityManager->remove($classe);
            $this->entityManager->flush();

            $this->addFlash('success', 'Class deleted successfully!');
        }

        return $this->redirectToRoute('app_classe');
    }

    #[Route('/{id}/enroll/{studentId}', name: 'app_classe_enroll_student', requirements: ['id' => '\d+', 'studentId' => '\d+'], methods: ['POST'])]
    public function enrollStudent(Request $request, Classe $classe, int $studentId): Response
    {
        if (!$this->isCsrfTokenValid('enroll'.$classe->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Check if class is full
        if ($classe->isFull()) {
            $this->addFlash('error', 'This class is full. Cannot enroll more students.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $student = $this->entityManager->getRepository(User::class)->find($studentId);
        if (!$student) {
            $this->addFlash('error', 'Student not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Check if already enrolled
        if ($this->studentClasseRepository->isStudentEnrolled($student, $classe)) {
            $this->addFlash('warning', 'Student is already enrolled in this class.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Create enrollment
        $studentClasse = new StudentClasse();
        $studentClasse->setStudent($student);
        $studentClasse->setClasse($classe);
        $studentClasse->setEnrolledAt(new \DateTime());
        $studentClasse->setIsActive(true);

        $this->entityManager->persist($studentClasse);

        // Auto-update status if class becomes full
        if ($classe->getStudentCount() + 1 >= $classe->getCapacity()) {
            $classe->setStatus(ClasseStatus::FULL);
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Student "%s" enrolled successfully!', $student->getEmail()));
        return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
    }

    #[Route('/{id}/unenroll/{studentClasseId}', name: 'app_classe_unenroll_student', requirements: ['id' => '\d+', 'studentClasseId' => '\d+'], methods: ['POST'])]
    public function unenrollStudent(Request $request, Classe $classe, int $studentClasseId): Response
    {
        if (!$this->isCsrfTokenValid('unenroll'.$classe->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $studentClasse = $this->studentClasseRepository->find($studentClasseId);
        if (!$studentClasse || $studentClasse->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Enrollment not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $studentEmail = $studentClasse->getStudent()->getEmail();
        $this->entityManager->remove($studentClasse);

        // Auto-update status if class was full
        if ($classe->getStatus() === ClasseStatus::FULL) {
            $classe->setStatus(ClasseStatus::ACTIVE);
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Student "%s" removed from class.', $studentEmail));
        return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
    }

    #[Route('/{id}/toggle-status/{studentClasseId}', name: 'app_classe_toggle_student_status', requirements: ['id' => '\d+', 'studentClasseId' => '\d+'], methods: ['POST'])]
    public function toggleStudentStatus(Request $request, Classe $classe, int $studentClasseId): Response
    {
        if (!$this->isCsrfTokenValid('toggle'.$classe->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $studentClasse = $this->studentClasseRepository->find($studentClasseId);
        if (!$studentClasse || $studentClasse->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Enrollment not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $studentClasse->setIsActive(!$studentClasse->isActive());
        $this->entityManager->flush();

        $status = $studentClasse->isActive() ? 'activated' : 'deactivated';
        $this->addFlash('success', sprintf('Student enrollment %s.', $status));
        return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
    }
}
