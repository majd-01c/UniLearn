<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Entity\ClasseModule;
use App\Entity\Module;
use App\Entity\StudentClasse;
use App\Entity\TeacherClasse;
use App\Entity\User;
use App\Enum\ClasseStatus;
use App\Enum\PeriodUnit;
use App\Form\ClasseType;
use App\Repository\ClasseRepository;
use App\Repository\StudentClasseRepository;
use App\Repository\TeacherClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/classe')]
#[IsGranted('ROLE_ADMIN')]
class ClasseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClasseRepository $classeRepository,
        private StudentClasseRepository $studentClasseRepository,
        private TeacherClasseRepository $teacherClasseRepository
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
        
        // Get available students (not enrolled in ANY class) - only STUDENT role
        $allStudents = $this->entityManager->getRepository(User::class)->findBy(['role' => 'STUDENT']);
        $availableStudents = array_filter(
            $allStudents,
            fn(User $user) => !$this->studentClasseRepository->isStudentEnrolledInAnyClass($user)
        );

        // Get assigned teachers
        $assignedTeachers = $this->teacherClasseRepository->findByClasse($classe);
        
        // Get available teachers (not assigned to this class) - only TEACHER role
        $assignedTeacherIds = array_map(
            fn(TeacherClasse $tc) => $tc->getTeacher()->getId(),
            $assignedTeachers
        );
        
        $allTeachers = $this->entityManager->getRepository(User::class)->findBy(['role' => 'TEACHER']);
        $availableTeachers = array_filter(
            $allTeachers,
            fn(User $user) => !in_array($user->getId(), $assignedTeacherIds)
        );

        // Get available programs for assignment
        $programs = $this->entityManager->getRepository(\App\Entity\Program::class)->findAll();

        return $this->render('Gestion_Program/classe/show.html.twig', [
            'classe' => $classe,
            'enrolledStudents' => $enrolledStudents,
            'availableStudents' => $availableStudents,
            'assignedTeachers' => $assignedTeachers,
            'availableTeachers' => $availableTeachers,
            'programs' => $programs,
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

        // Check if already enrolled in THIS class
        if ($this->studentClasseRepository->isStudentEnrolled($student, $classe)) {
            $this->addFlash('warning', 'Student is already enrolled in this class.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Check if already enrolled in ANY other class
        $existingEnrollment = $this->studentClasseRepository->findStudentCurrentEnrollment($student);
        if ($existingEnrollment) {
            $existingClassName = $existingEnrollment->getClasse()->getName();
            $this->addFlash('error', sprintf('Student is already enrolled in class "%s". A student can only be enrolled in one class at a time.', $existingClassName));
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

    #[Route('/{id}/assign-teacher/{teacherId}', name: 'app_classe_assign_teacher', requirements: ['id' => '\d+', 'teacherId' => '\d+'], methods: ['POST'])]
    public function assignTeacher(Request $request, Classe $classe, int $teacherId): Response
    {
        if (!$this->isCsrfTokenValid('assign_teacher'.$classe->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $teacher = $this->entityManager->getRepository(User::class)->find($teacherId);
        if (!$teacher) {
            $this->addFlash('error', 'Teacher not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Verify the user is a teacher (not admin or business partner)
        if ($teacher->getRole() !== 'TEACHER') {
            $this->addFlash('error', 'Only users with TEACHER role can be assigned to a class. Admins and Business Partners cannot be assigned.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Check if already assigned
        if ($this->teacherClasseRepository->isTeacherAssigned($teacher, $classe)) {
            $this->addFlash('warning', 'Teacher is already assigned to this class.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Create assignment
        $teacherClasse = new TeacherClasse();
        $teacherClasse->setTeacher($teacher);
        $teacherClasse->setClasse($classe);
        $teacherClasse->setAssignedAt(new \DateTime());
        $teacherClasse->setIsActive(true);

        $this->entityManager->persist($teacherClasse);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Teacher "%s" assigned successfully!', $teacher->getEmail()));
        return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
    }

    #[Route('/{id}/unassign-teacher/{teacherClasseId}', name: 'app_classe_unassign_teacher', requirements: ['id' => '\d+', 'teacherClasseId' => '\d+'], methods: ['POST'])]
    public function unassignTeacher(Request $request, Classe $classe, int $teacherClasseId): Response
    {
        if (!$this->isCsrfTokenValid('unassign_teacher'.$classe->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        if (!$teacherClasse || $teacherClasse->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Teacher assignment not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $teacherEmail = $teacherClasse->getTeacher()->getEmail();
        $this->entityManager->remove($teacherClasse);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Teacher "%s" removed from class.', $teacherEmail));
        return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
    }

    #[Route('/{id}/toggle-teacher-status/{teacherClasseId}', name: 'app_classe_toggle_teacher_status', requirements: ['id' => '\d+', 'teacherClasseId' => '\d+'], methods: ['POST'])]
    public function toggleTeacherStatus(Request $request, Classe $classe, int $teacherClasseId): Response
    {
        if (!$this->isCsrfTokenValid('toggle_teacher'.$classe->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        if (!$teacherClasse || $teacherClasse->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Teacher assignment not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $teacherClasse->setIsActive(!$teacherClasse->isActive());
        $this->entityManager->flush();

        $status = $teacherClasse->isActive() ? 'activated' : 'deactivated';
        $this->addFlash('success', sprintf('Teacher assignment %s.', $status));
        return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
    }

    #[Route('/{id}/teacher/{teacherClasseId}/create-module', name: 'app_classe_teacher_create_module', requirements: ['id' => '\d+', 'teacherClasseId' => '\d+'], methods: ['GET', 'POST'])]
    public function teacherCreateModule(Request $request, Classe $classe, int $teacherClasseId): Response
    {
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        if (!$teacherClasse || $teacherClasse->getClasse()->getId() !== $classe->getId()) {
            $this->addFlash('error', 'Teacher assignment not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Check if teacher already has a module
        if ($teacherClasse->hasCreatedModule()) {
            $this->addFlash('warning', 'This teacher has already created a module for this class.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        if ($request->isMethod('POST')) {
            $moduleName = $request->request->get('module_name');
            $duration = (int) $request->request->get('duration', 1);
            $periodUnit = $request->request->get('period_unit', 'WEEK');

            if (empty($moduleName)) {
                $this->addFlash('error', 'Module name is required.');
                return $this->render('Gestion_Program/classe/create_module.html.twig', [
                    'classe' => $classe,
                    'teacherClasse' => $teacherClasse,
                ]);
            }

            // Create new module
            $module = new Module();
            $module->setName($moduleName);
            $module->setDuration($duration);
            $module->setPeriodUnit(PeriodUnit::from($periodUnit));

            $this->entityManager->persist($module);

            // Link module to teacher assignment
            $teacherClasse->setModule($module);
            $teacherClasse->setHasCreatedModule(true);

            // Create ClasseModule to link the module to the class
            $classeModule = new ClasseModule();
            $classeModule->setClasse($classe);
            $classeModule->setModule($module);

            $this->entityManager->persist($classeModule);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Module "%s" created successfully!', $moduleName));
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        return $this->render('Gestion_Program/classe/create_module.html.twig', [
            'classe' => $classe,
            'teacherClasse' => $teacherClasse,
        ]);
    }

    #[Route('/{id}/assign-program', name: 'app_classe_assign_program', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assignProgram(Request $request, Classe $classe): Response
    {
        if (!$this->isCsrfTokenValid('assign_program'.$classe->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        // Check if all teachers have created their modules
        if (!$classe->canAssignProgram()) {
            $this->addFlash('error', 'Cannot assign program. All teachers must create their modules first.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $programId = $request->request->get('program_id');
        if (!$programId) {
            $this->addFlash('error', 'Please select a program.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $program = $this->entityManager->getRepository(\App\Entity\Program::class)->find($programId);
        if (!$program) {
            $this->addFlash('error', 'Program not found.');
            return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
        }

        $classe->setProgram($program);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Program "%s" assigned to class successfully!', $program->getName()));
        return $this->redirectToRoute('app_classe_show', ['id' => $classe->getId()]);
    }
}
