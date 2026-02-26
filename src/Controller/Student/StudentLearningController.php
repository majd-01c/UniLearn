<?php

namespace App\Controller\Student;

use App\Entity\Answer;
use App\Entity\Choice;
use App\Entity\ClasseContenu;
use App\Entity\ClasseCourse;
use App\Entity\ClasseModule;
use App\Entity\ClassMeeting;
use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\StudentClasse;
use App\Entity\User;
use App\Entity\UserAnswer;
use App\Enum\QuestionType;
use App\Repository\ClassMeetingRepository;
use App\Repository\QuizRepository;
use App\Repository\StudentClasseRepository;
use App\Repository\UserAnswerRepository;
use App\Service\AI\GeminiAIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student/learn')]
#[IsGranted('ROLE_STUDENT')]
class StudentLearningController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentClasseRepository $studentClasseRepository,
        private QuizRepository $quizRepository,
        private UserAnswerRepository $userAnswerRepository,
        private ClassMeetingRepository $classMeetingRepository,
        private GeminiAIService $geminiAIService
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

    #[Route('/classe/{classeId}/quiz/{quizId}', name: 'app_student_quiz_view', requirements: ['classeId' => '\d+', 'quizId' => '\d+'])]
    public function viewQuiz(int $classeId, int $quizId): Response
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

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz) {
            $this->addFlash('error', 'Quiz not found.');
            return $this->redirectToRoute('app_student_classe_view', ['id' => $classeId]);
        }

        // Check if student has already completed this quiz
        $existingAnswer = $this->userAnswerRepository->findOneBy([
            'user' => $user,
            'quiz' => $quiz
        ]);

        if ($existingAnswer && $existingAnswer->getCompletedAt()) {
            // Show results instead
            return $this->render('Gestion_Program/student_learning/quiz_result.html.twig', [
                'classe' => $enrollment->getClasse(),
                'quiz' => $quiz,
                'userAnswer' => $existingAnswer,
            ]);
        }

        // Get questions (shuffle if enabled)
        $questions = $quiz->getQuestions()->toArray();
        if ($quiz->isShuffleQuestions()) {
            shuffle($questions);
        }

        // Shuffle choices if enabled
        if ($quiz->isShuffleChoices()) {
            foreach ($questions as $question) {
                $choices = $question->getChoices()->toArray();
                shuffle($choices);
                // Store shuffled order in session or just pass as is for now
            }
        }

        return $this->render('Gestion_Program/student_learning/quiz_take.html.twig', [
            'classe' => $enrollment->getClasse(),
            'quiz' => $quiz,
            'questions' => $questions,
            'existingAnswer' => $existingAnswer,
        ]);
    }

    #[Route('/classe/{classeId}/quiz/{quizId}/submit', name: 'app_student_quiz_submit', requirements: ['classeId' => '\d+', 'quizId' => '\d+'], methods: ['POST'])]
    public function submitQuiz(Request $request, int $classeId, int $quizId): Response
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

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz) {
            $this->addFlash('error', 'Quiz not found.');
            return $this->redirectToRoute('app_student_classe_view', ['id' => $classeId]);
        }

        if (!$this->isCsrfTokenValid('submit_quiz'.$quizId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_student_quiz_view', [
                'classeId' => $classeId,
                'quizId' => $quizId
            ]);
        }

        // Check if already completed
        $existingAnswer = $this->userAnswerRepository->findOneBy([
            'user' => $user,
            'quiz' => $quiz
        ]);

        if ($existingAnswer && $existingAnswer->getCompletedAt()) {
            $this->addFlash('info', 'You have already completed this quiz.');
            return $this->redirectToRoute('app_student_quiz_view', [
                'classeId' => $classeId,
                'quizId' => $quizId
            ]);
        }

        // Create or get UserAnswer
        $userAnswer = $existingAnswer ?? new UserAnswer();
        if (!$existingAnswer) {
            $userAnswer->setUser($user);
            $userAnswer->setQuiz($quiz);
            $this->entityManager->persist($userAnswer);
        }

        // Process answers
        $totalScore = 0;
        $totalPoints = 0;
        $answers = $request->request->all('answers');
        $aiFeedback = []; // Store AI feedback for TEXT questions

        foreach ($quiz->getQuestions() as $question) {
            $totalPoints += $question->getPoints();
            $questionId = $question->getId();
            $selectedAnswer = $answers[$questionId] ?? null;
            $questionType = $question->getType();

            // Handle TEXT type questions with AI grading
            if ($questionType === QuestionType::TEXT) {
                $answer = new Answer();
                $answer->setUserAnswer($userAnswer);
                $answer->setQuestion($question);
                $answer->setTextAnswer($selectedAnswer);

                if (!empty(trim($selectedAnswer ?? ''))) {
                    try {
                        // Get expected answer from explanation if available
                        $expectedAnswer = $question->getExplanation() ?? $question->getQuestionText();
                        
                        // Use AI to evaluate the answer
                        $evaluation = $this->geminiAIService->evaluateTextAnswer(
                            $question->getQuestionText(),
                            $expectedAnswer,
                            $selectedAnswer,
                            $question->getPoints()
                        );

                        $answer->setIsCorrect($evaluation['isCorrect']);
                        $answer->setPointsEarned($evaluation['score']);
                        $totalScore += $evaluation['score'];
                        
                        // Store feedback for display
                        $aiFeedback[$questionId] = $evaluation['feedback'];
                    } catch (\Exception $e) {
                        // If AI grading fails, mark for manual review (give 0 points)
                        $answer->setIsCorrect(false);
                        $answer->setPointsEarned(0);
                        $aiFeedback[$questionId] = 'Pending manual review.';
                    }
                } else {
                    $answer->setIsCorrect(false);
                    $answer->setPointsEarned(0);
                }

                $this->entityManager->persist($answer);
                continue;
            }

            // Get correct choices for this question
            $correctChoices = $question->getCorrectChoices();
            $correctChoiceIds = array_map(fn($c) => $c->getId(), $correctChoices->toArray());
            $totalCorrectChoices = count($correctChoiceIds);

            // Check if this is a multi-select answer (array) or single (string/int)
            if (is_array($selectedAnswer)) {
                // Multi-select MCQ - calculate partial credit
                $selectedIds = array_map('intval', $selectedAnswer);
                $correctSelected = 0;
                $incorrectSelected = 0;
                
                foreach ($selectedIds as $selectedId) {
                    if (in_array($selectedId, $correctChoiceIds)) {
                        $correctSelected++;
                    } else {
                        $incorrectSelected++;
                    }
                }
                
                // Calculate score: correct selections minus incorrect selections, minimum 0
                // Full points only if ALL correct answers selected and NO incorrect answers
                $questionScore = 0;
                if ($totalCorrectChoices > 0) {
                    if ($correctSelected === $totalCorrectChoices && $incorrectSelected === 0) {
                        // Perfect answer - full points
                        $questionScore = $question->getPoints();
                    } else {
                        // Partial credit: (correct - incorrect) / totalCorrect * points
                        $netCorrect = max(0, $correctSelected - $incorrectSelected);
                        $questionScore = (int) round(($netCorrect / $totalCorrectChoices) * $question->getPoints());
                    }
                }
                
                $totalScore += $questionScore;
                
                // Create Answer record with first selected choice for reference
                $answer = new Answer();
                $answer->setUserAnswer($userAnswer);
                $answer->setQuestion($question);
                
                if (!empty($selectedIds)) {
                    $firstChoice = $this->entityManager->getRepository(Choice::class)->find($selectedIds[0]);
                    $answer->setSelectedChoice($firstChoice);
                    // Store all selected IDs in textAnswer for reference
                    $answer->setTextAnswer(json_encode($selectedIds));
                }
                
                $isFullyCorrect = ($correctSelected === $totalCorrectChoices && $incorrectSelected === 0);
                $answer->setIsCorrect($isFullyCorrect);
                $answer->setPointsEarned($questionScore);
                
                $this->entityManager->persist($answer);
            } else {
                // Single select (radio button) - original logic
                $answer = new Answer();
                $answer->setUserAnswer($userAnswer);
                $answer->setQuestion($question);

                if ($selectedAnswer) {
                    $choice = $this->entityManager->getRepository(Choice::class)->find($selectedAnswer);
                    if ($choice && $choice->getQuestion()->getId() === $question->getId()) {
                        $answer->setSelectedChoice($choice);
                        
                        if ($choice->isCorrect()) {
                            $answer->setIsCorrect(true);
                            $answer->setPointsEarned($question->getPoints());
                            $totalScore += $question->getPoints();
                        } else {
                            $answer->setIsCorrect(false);
                            $answer->setPointsEarned(0);
                        }
                    }
                } else {
                    $answer->setIsCorrect(false);
                    $answer->setPointsEarned(0);
                }

                $this->entityManager->persist($answer);
            }
        }

        // Update UserAnswer
        $userAnswer->setScore($totalScore);
        $userAnswer->setTotalPoints($totalPoints);
        $userAnswer->setCompletedAt(new \DateTime());
        
        // Check if passed
        $percentage = $totalPoints > 0 ? ($totalScore / $totalPoints * 100) : 0;
        $userAnswer->setIsPassed($percentage >= ($quiz->getPassingScore() ?? 50));

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Quiz submitted! Your score: %d/%d (%.0f%%)', $totalScore, $totalPoints, $percentage));
        
        return $this->redirectToRoute('app_student_quiz_view', [
            'classeId' => $classeId,
            'quizId' => $quizId
        ]);
    }

    #[Route('/classe/{classeId}/meetings', name: 'app_student_meetings', requirements: ['classeId' => '\d+'])]
    public function viewMeetings(int $classeId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
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
        $meetings = $this->classMeetingRepository->findUpcomingMeetingsForClasse($classeId);
        $liveMeetings = $this->classMeetingRepository->findLiveMeetingsForClasse($classeId);

        return $this->render('Gestion_Program/student_learning/meetings.html.twig', [
            'classe' => $classe,
            'enrollment' => $enrollment,
            'meetings' => $meetings,
            'liveMeetings' => $liveMeetings,
        ]);
    }

    #[Route('/meeting/{meetingId}/join', name: 'app_student_meeting_join', requirements: ['meetingId' => '\d+'])]
    public function joinMeeting(int $meetingId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $meeting = $this->classMeetingRepository->find($meetingId);
        if (!$meeting) {
            $this->addFlash('error', 'Meeting not found.');
            return $this->redirectToRoute('app_student_learning_index');
        }

        // Verify student is enrolled in the class
        $classeId = $meeting->getTeacherClasse()->getClasse()->getId();
        $enrollment = $this->studentClasseRepository->findOneBy([
            'student' => $user,
            'classe' => $classeId,
            'isActive' => true
        ]);

        if (!$enrollment) {
            $this->addFlash('error', 'You are not enrolled in this class.');
            return $this->redirectToRoute('app_student_learning_index');
        }

        if (!$meeting->isLive()) {
            $this->addFlash('warning', 'This meeting is not currently active.');
            return $this->redirectToRoute('app_student_meetings', ['classeId' => $classeId]);
        }

        return $this->render('Gestion_Program/student_learning/meeting_room.html.twig', [
            'meeting' => $meeting,
            'classe' => $enrollment->getClasse(),
            'jitsi_host' => $_ENV['JITSI_HOST'] ?? 'meet.jit.si',
            'username' => $user->getName(),
        ]);
    }
}
