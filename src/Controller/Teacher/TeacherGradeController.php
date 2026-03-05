<?php

namespace App\Controller\Teacher;

use App\Entity\Assessment;
use App\Entity\Grade;
use App\Entity\StudentClasse;
use App\Entity\User;
use App\Enum\AssessmentType;
use App\Repository\AssessmentRepository;
use App\Repository\GradeRepository;
use App\Repository\StudentClasseRepository;
use App\Repository\TeacherClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/teacher/grades')]
#[IsGranted('ROLE_TEACHER')]
class TeacherGradeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeacherClasseRepository $teacherClasseRepository,
        private StudentClasseRepository $studentClasseRepository,
        private AssessmentRepository $assessmentRepository,
        private GradeRepository $gradeRepository
    ) {}

    #[Route('', name: 'app_teacher_grades')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasses = $this->teacherClasseRepository->findByTeacher($user);

        // Get assessments created by this teacher
        $assessments = $this->assessmentRepository->findBy(
            ['teacher' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('Gestion_Evaluation/teacher_grade/index.html.twig', [
            'teacherClasses' => $teacherClasses,
            'assessments' => $assessments,
        ]);
    }

    #[Route('/classe/{id}', name: 'app_teacher_grades_classe', requirements: ['id' => '\d+'])]
    public function classeGrades(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($id);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Class not found or you are not assigned to it.');
            return $this->redirectToRoute('app_teacher_grades');
        }

        $classe = $teacherClasse->getClasse();
        
        // Get assessments for this class by this teacher
        $assessments = $this->assessmentRepository->findBy(
            ['teacher' => $user, 'classe' => $classe],
            ['createdAt' => 'DESC']
        );

        // Get students in this class
        $studentClasses = $this->studentClasseRepository->findByClasse($classe);

        return $this->render('Gestion_Evaluation/teacher_grade/classe.html.twig', [
            'teacherClasse' => $teacherClasse,
            'classe' => $classe,
            'assessments' => $assessments,
            'studentClasses' => $studentClasses,
        ]);
    }

    #[Route('/classe/{id}/create', name: 'app_teacher_assessment_create', requirements: ['id' => '\d+'])]
    public function createAssessment(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($id);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_grades');
        }

        $classe = $teacherClasse->getClasse();

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $typeValue = $request->request->get('type');
            $description = $request->request->get('description');
            $maxScore = (float) $request->request->get('max_score', 20);
            $dateStr = $request->request->get('date');

            if (!$title || !$typeValue) {
                $this->addFlash('error', 'Title and type are required.');
                return $this->redirectToRoute('app_teacher_assessment_create', ['id' => $id]);
            }

            $type = AssessmentType::tryFrom($typeValue);
            if (!$type || !in_array($type, [AssessmentType::CC, AssessmentType::EXAM])) {
                $this->addFlash('error', 'Invalid assessment type. Only CC and EXAM are allowed.');
                return $this->redirectToRoute('app_teacher_assessment_create', ['id' => $id]);
            }

            $assessment = new Assessment();
            $assessment->setTitle($title);
            $assessment->setType($type);
            $assessment->setDescription($description);
            $assessment->setMaxScore($maxScore);
            $assessment->setTeacher($user);
            $assessment->setClasse($classe);

            if ($dateStr) {
                $assessment->setDate(new \DateTime($dateStr));
            }

            // Set the course from the teacher's module
            if ($teacherClasse->getModule()) {
                // Find a course related to this module
                $moduleCourses = $teacherClasse->getModule()->getCourses();
                if (!$moduleCourses->isEmpty()) {
                    $assessment->setCourse($moduleCourses->first()->getCourse());
                }
            }

            $this->entityManager->persist($assessment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Assessment created successfully.');
            return $this->redirectToRoute('app_teacher_grades_classe', ['id' => $id]);
        }

        return $this->render('Gestion_Evaluation/teacher_grade/create_assessment.html.twig', [
            'teacherClasse' => $teacherClasse,
            'classe' => $classe,
        ]);
    }

    #[Route('/assessment/{id}', name: 'app_teacher_assessment_grades', requirements: ['id' => '\d+'])]
    public function assessmentGrades(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $assessment = $this->assessmentRepository->find($id);
        
        if (!$assessment || $assessment->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Assessment not found or you are not the owner.');
            return $this->redirectToRoute('app_teacher_grades');
        }

        $classe = $assessment->getClasse();
        if (!$classe) {
            $this->addFlash('error', 'Assessment is not linked to a class.');
            return $this->redirectToRoute('app_teacher_grades');
        }

        // Find teacherClasse for this classe
        $teacherClasse = $this->teacherClasseRepository->findOneBy([
            'teacher' => $user,
            'classe' => $classe
        ]);

        // Get students in this class
        $studentClasses = $this->studentClasseRepository->findByClasse($classe);

        // Get existing grades for this assessment
        $grades = $this->gradeRepository->findBy(['assessment' => $assessment]);
        $gradesMap = [];
        foreach ($grades as $grade) {
            $gradesMap[$grade->getStudent()->getId()] = $grade;
        }

        return $this->render('Gestion_Evaluation/teacher_grade/assessment_grades.html.twig', [
            'assessment' => $assessment,
            'classe' => $classe,
            'teacherClasse' => $teacherClasse,
            'studentClasses' => $studentClasses,
            'gradesMap' => $gradesMap,
        ]);
    }

    #[Route('/assessment/{id}/save', name: 'app_teacher_save_grades', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveGrades(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $assessment = $this->assessmentRepository->find($id);
        
        if (!$assessment || $assessment->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Assessment not found or you are not the owner.');
            return $this->redirectToRoute('app_teacher_grades');
        }

        if (!$this->isCsrfTokenValid('save_grades_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_assessment_grades', ['id' => $id]);
        }

        $scores = $request->request->all('scores');
        $comments = $request->request->all('comments');

        $studentRepo = $this->entityManager->getRepository(User::class);

        foreach ($scores as $studentId => $scoreValue) {
            if ($scoreValue === '' || $scoreValue === null) {
                continue;
            }

            $score = (float) $scoreValue;
            if ($score < 0 || $score > $assessment->getMaxScore()) {
                continue;
            }

            $student = $studentRepo->find($studentId);
            if (!$student) {
                continue;
            }

            // Find existing grade or create new one
            $grade = $this->gradeRepository->findOneBy([
                'assessment' => $assessment,
                'student' => $student
            ]);

            if (!$grade) {
                $grade = new Grade();
                $grade->setAssessment($assessment);
                $grade->setStudent($student);
                $grade->setTeacher($user);
            }

            $grade->setScore($score);
            $grade->setComment($comments[$studentId] ?? null);
            $grade->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($grade);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Grades saved successfully.');
        return $this->redirectToRoute('app_teacher_assessment_grades', ['id' => $id]);
    }

    #[Route('/assessment/{id}/delete', name: 'app_teacher_assessment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAssessment(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $assessment = $this->assessmentRepository->find($id);
        
        if (!$assessment || $assessment->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Assessment not found or you are not the owner.');
            return $this->redirectToRoute('app_teacher_grades');
        }

        $classeId = null;
        $teacherClasse = $this->teacherClasseRepository->findOneBy([
            'teacher' => $user,
            'classe' => $assessment->getClasse()
        ]);
        if ($teacherClasse) {
            $classeId = $teacherClasse->getId();
        }

        if (!$this->isCsrfTokenValid('delete_assessment_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_grades');
        }

        $this->entityManager->remove($assessment);
        $this->entityManager->flush();

        $this->addFlash('success', 'Assessment deleted successfully.');
        
        if ($classeId) {
            return $this->redirectToRoute('app_teacher_grades_classe', ['id' => $classeId]);
        }
        return $this->redirectToRoute('app_teacher_grades');
    }
}
