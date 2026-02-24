<?php

namespace App\Controller\Teacher;

use App\Entity\Answer;
use App\Entity\Choice;
use App\Entity\ClasseContenu;
use App\Entity\ClasseCourse;
use App\Entity\Contenu;
use App\Entity\CourseContenu;
use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\User;
use App\Entity\UserAnswer;
use App\Enum\ContenuType;
use App\Enum\QuestionType;
use App\Repository\QuizRepository;
use App\Repository\TeacherClasseRepository;
use App\Repository\UserAnswerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/teacher/quiz')]
#[IsGranted('ROLE_TEACHER')]
class TeacherQuizController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeacherClasseRepository $teacherClasseRepository,
        private QuizRepository $quizRepository,
        private UserAnswerRepository $userAnswerRepository
    ) {}

    #[Route('/{teacherClasseId}/course/{courseId}/create', name: 'app_teacher_quiz_create', requirements: ['teacherClasseId' => '\d+', 'courseId' => '\d+'], methods: ['GET', 'POST'])]
    public function create(Request $request, int $teacherClasseId, int $courseId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $classeCourse = $this->entityManager->getRepository(ClasseCourse::class)->find($courseId);
        if (!$classeCourse) {
            $this->addFlash('error', 'Course not found.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        // Verify the course belongs to the teacher's module
        $classeModule = $classeCourse->getClasseModule();
        if (!$classeModule || !$teacherClasse->getModule() || 
            $classeModule->getModule()->getId() !== $teacherClasse->getModule()->getId()) {
            $this->addFlash('error', 'You can only add quizzes to your own module.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_quiz'.$teacherClasseId, $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_teacher_quiz_create', [
                    'teacherClasseId' => $teacherClasseId,
                    'courseId' => $courseId
                ]);
            }

            $quizTitle = trim($request->request->get('quiz_title', ''));
            $quizDescription = trim($request->request->get('quiz_description', ''));
            $passingScore = (int) $request->request->get('passing_score', 50);
            $timeLimit = $request->request->get('time_limit') ? (int) $request->request->get('time_limit') : null;
            $shuffleQuestions = $request->request->has('shuffle_questions');
            $shuffleChoices = $request->request->has('shuffle_choices');
            $showCorrectAnswers = $request->request->has('show_correct_answers');

            if (empty($quizTitle)) {
                $this->addFlash('error', 'Quiz title is required.');
                return $this->render('Gestion_Program/teacher_quiz/create.html.twig', [
                    'teacherClasse' => $teacherClasse,
                    'classeCourse' => $classeCourse,
                ]);
            }

            // Create the Contenu for the quiz
            $contenu = new Contenu();
            $contenu->setTitle($quizTitle);
            $contenu->setType(ContenuType::QUIZ);
            $contenu->setPublished(true);
            $contenu->setCreatedAt(new \DateTime());
            $contenu->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($contenu);

            // Create the Quiz
            $quiz = new Quiz();
            $quiz->setContenu($contenu);
            $quiz->setTitle($quizTitle);
            $quiz->setDescription($quizDescription ?: null);
            $quiz->setPassingScore($passingScore);
            $quiz->setTimeLimit($timeLimit);
            $quiz->setShuffleQuestions($shuffleQuestions);
            $quiz->setShuffleChoices($shuffleChoices);
            $quiz->setShowCorrectAnswers($showCorrectAnswers);

            $this->entityManager->persist($quiz);

            // Link contenu to course
            $course = $classeCourse->getCourse();
            $courseContenu = new CourseContenu();
            $courseContenu->setCourse($course);
            $courseContenu->setContenu($contenu);
            $this->entityManager->persist($courseContenu);

            // Create ClasseContenu for visibility
            $classeContenu = new ClasseContenu();
            $classeContenu->setClasseCourse($classeCourse);
            $classeContenu->setContenu($contenu);
            $classeContenu->setIsHidden(false);
            $this->entityManager->persist($classeContenu);

            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Quiz "%s" created! Now add questions to it.', $quizTitle));
            return $this->redirectToRoute('app_teacher_quiz_edit', [
                'teacherClasseId' => $teacherClasseId,
                'quizId' => $quiz->getId()
            ]);
        }

        return $this->render('Gestion_Program/teacher_quiz/create.html.twig', [
            'teacherClasse' => $teacherClasse,
            'classeCourse' => $classeCourse,
        ]);
    }

    #[Route('/{teacherClasseId}/edit/{quizId}', name: 'app_teacher_quiz_edit', requirements: ['teacherClasseId' => '\d+', 'quizId' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $teacherClasseId, int $quizId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz) {
            $this->addFlash('error', 'Quiz not found.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        // Verify quiz belongs to teacher's module
        if (!$this->verifyQuizBelongsToTeacher($quiz, $teacherClasse)) {
            $this->addFlash('error', 'You can only edit quizzes in your own module.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_quiz'.$teacherClasseId, $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_teacher_quiz_edit', [
                    'teacherClasseId' => $teacherClasseId,
                    'quizId' => $quizId
                ]);
            }

            $quizTitle = trim($request->request->get('quiz_title', ''));
            $quizDescription = trim($request->request->get('quiz_description', ''));
            $passingScore = (int) $request->request->get('passing_score', 50);
            $timeLimit = $request->request->get('time_limit') ? (int) $request->request->get('time_limit') : null;
            $shuffleQuestions = $request->request->has('shuffle_questions');
            $shuffleChoices = $request->request->has('shuffle_choices');
            $showCorrectAnswers = $request->request->has('show_correct_answers');

            if (empty($quizTitle)) {
                $this->addFlash('error', 'Quiz title is required.');
                return $this->render('Gestion_Program/teacher_quiz/edit.html.twig', [
                    'teacherClasse' => $teacherClasse,
                    'quiz' => $quiz,
                    'questionTypes' => QuestionType::cases(),
                ]);
            }

            $quiz->setTitle($quizTitle);
            $quiz->setDescription($quizDescription ?: null);
            $quiz->setPassingScore($passingScore);
            $quiz->setTimeLimit($timeLimit);
            $quiz->setShuffleQuestions($shuffleQuestions);
            $quiz->setShuffleChoices($shuffleChoices);
            $quiz->setShowCorrectAnswers($showCorrectAnswers);

            // Update contenu title as well
            $contenu = $quiz->getContenu();
            if ($contenu) {
                $contenu->setTitle($quizTitle);
                $contenu->setUpdatedAt(new \DateTime());
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Quiz settings updated successfully!');
        }

        return $this->render('Gestion_Program/teacher_quiz/edit.html.twig', [
            'teacherClasse' => $teacherClasse,
            'quiz' => $quiz,
            'questionTypes' => QuestionType::cases(),
        ]);
    }

    #[Route('/{teacherClasseId}/quiz/{quizId}/question/add', name: 'app_teacher_quiz_question_add', requirements: ['teacherClasseId' => '\d+', 'quizId' => '\d+'], methods: ['POST'])]
    public function addQuestion(Request $request, int $teacherClasseId, int $quizId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz || !$this->verifyQuizBelongsToTeacher($quiz, $teacherClasse)) {
            return $this->json(['error' => 'Quiz not found'], 404);
        }

        if (!$this->isCsrfTokenValid('add_question'.$quizId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_quiz_edit', [
                'teacherClasseId' => $teacherClasseId,
                'quizId' => $quizId
            ]);
        }

        $questionText = trim($request->request->get('question_text', ''));
        $questionType = $request->request->get('question_type', 'MCQ');
        $points = (int) $request->request->get('points', 1);
        $explanation = trim($request->request->get('explanation', ''));
        $choicesData = $request->request->all('choices');
        $correctChoices = $request->request->all('correct_choices');

        if (empty($questionText)) {
            $this->addFlash('error', 'Question text is required.');
            return $this->redirectToRoute('app_teacher_quiz_edit', [
                'teacherClasseId' => $teacherClasseId,
                'quizId' => $quizId
            ]);
        }

        // Validate choices for MCQ and TRUE_FALSE
        if (in_array($questionType, ['MCQ', 'TRUE_FALSE'])) {
            $validChoices = array_filter($choicesData, fn($c) => trim($c) !== '');
            if (count($validChoices) < 2) {
                $this->addFlash('error', 'MCQ questions require at least 2 answer choices.');
                return $this->redirectToRoute('app_teacher_quiz_edit', [
                    'teacherClasseId' => $teacherClasseId,
                    'quizId' => $quizId
                ]);
            }
            if (empty($correctChoices)) {
                $this->addFlash('error', 'Please mark at least one correct answer.');
                return $this->redirectToRoute('app_teacher_quiz_edit', [
                    'teacherClasseId' => $teacherClasseId,
                    'quizId' => $quizId
                ]);
            }
        }

        // Create the question
        $question = new Question();
        $question->setQuiz($quiz);
        $question->setQuestionText($questionText);
        $question->setType(QuestionType::from($questionType));
        $question->setPoints($points);
        $question->setPosition($quiz->getQuestions()->count());
        $question->setExplanation($explanation ?: null);

        $this->entityManager->persist($question);

        // Add choices for MCQ or TRUE_FALSE
        if (in_array($questionType, ['MCQ', 'TRUE_FALSE'])) {
            $position = 0;
            foreach ($choicesData as $index => $choiceText) {
                $choiceText = trim($choiceText);
                if (!empty($choiceText)) {
                    $choice = new Choice();
                    $choice->setQuestion($question);
                    $choice->setChoiceText($choiceText);
                    $choice->setIsCorrect(in_array((string)$index, $correctChoices));
                    $choice->setPosition($position++);
                    $this->entityManager->persist($choice);
                }
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Question added successfully!');
        return $this->redirectToRoute('app_teacher_quiz_edit', [
            'teacherClasseId' => $teacherClasseId,
            'quizId' => $quizId
        ]);
    }

    #[Route('/{teacherClasseId}/quiz/{quizId}/question/{questionId}/edit', name: 'app_teacher_quiz_question_edit', requirements: ['teacherClasseId' => '\d+', 'quizId' => '\d+', 'questionId' => '\d+'], methods: ['GET', 'POST'])]
    public function editQuestion(Request $request, int $teacherClasseId, int $quizId, int $questionId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz || !$this->verifyQuizBelongsToTeacher($quiz, $teacherClasse)) {
            $this->addFlash('error', 'Quiz not found.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        $question = $this->entityManager->getRepository(Question::class)->find($questionId);
        if (!$question || $question->getQuiz()->getId() !== $quiz->getId()) {
            $this->addFlash('error', 'Question not found.');
            return $this->redirectToRoute('app_teacher_quiz_edit', [
                'teacherClasseId' => $teacherClasseId,
                'quizId' => $quizId
            ]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_question'.$questionId, $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('app_teacher_quiz_question_edit', [
                    'teacherClasseId' => $teacherClasseId,
                    'quizId' => $quizId,
                    'questionId' => $questionId
                ]);
            }

            $questionText = trim($request->request->get('question_text', ''));
            $questionType = $request->request->get('question_type', 'MCQ');
            $points = (int) $request->request->get('points', 1);
            $explanation = trim($request->request->get('explanation', ''));
            $choicesData = $request->request->all('choices');
            $correctChoices = $request->request->all('correct_choices');

            if (empty($questionText)) {
                $this->addFlash('error', 'Question text is required.');
                return $this->render('Gestion_Program/teacher_quiz/edit_question.html.twig', [
                    'teacherClasse' => $teacherClasse,
                    'quiz' => $quiz,
                    'question' => $question,
                    'questionTypes' => QuestionType::cases(),
                ]);
            }

            // Validate choices for MCQ and TRUE_FALSE
            if (in_array($questionType, ['MCQ', 'TRUE_FALSE'])) {
                $validChoices = array_filter($choicesData, fn($c) => trim($c) !== '');
                if (count($validChoices) < 2) {
                    $this->addFlash('error', 'MCQ questions require at least 2 answer choices.');
                    return $this->render('Gestion_Program/teacher_quiz/edit_question.html.twig', [
                        'teacherClasse' => $teacherClasse,
                        'quiz' => $quiz,
                        'question' => $question,
                        'questionTypes' => QuestionType::cases(),
                    ]);
                }
                if (empty($correctChoices)) {
                    $this->addFlash('error', 'Please mark at least one correct answer.');
                    return $this->render('Gestion_Program/teacher_quiz/edit_question.html.twig', [
                        'teacherClasse' => $teacherClasse,
                        'quiz' => $quiz,
                        'question' => $question,
                        'questionTypes' => QuestionType::cases(),
                    ]);
                }
            }

            $question->setQuestionText($questionText);
            $question->setType(QuestionType::from($questionType));
            $question->setPoints($points);
            $question->setExplanation($explanation ?: null);

            // Remove old choices
            foreach ($question->getChoices() as $choice) {
                $this->entityManager->remove($choice);
            }

            // Add new choices for MCQ or TRUE_FALSE
            if (in_array($questionType, ['MCQ', 'TRUE_FALSE'])) {
                $position = 0;
                foreach ($choicesData as $index => $choiceText) {
                    $choiceText = trim($choiceText);
                    if (!empty($choiceText)) {
                        $choice = new Choice();
                        $choice->setQuestion($question);
                        $choice->setChoiceText($choiceText);
                        $choice->setIsCorrect(in_array((string)$index, $correctChoices));
                        $choice->setPosition($position++);
                        $this->entityManager->persist($choice);
                    }
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Question updated successfully!');
            return $this->redirectToRoute('app_teacher_quiz_edit', [
                'teacherClasseId' => $teacherClasseId,
                'quizId' => $quizId
            ]);
        }

        return $this->render('Gestion_Program/teacher_quiz/edit_question.html.twig', [
            'teacherClasse' => $teacherClasse,
            'quiz' => $quiz,
            'question' => $question,
            'questionTypes' => QuestionType::cases(),
        ]);
    }

    #[Route('/{teacherClasseId}/quiz/{quizId}/question/{questionId}/delete', name: 'app_teacher_quiz_question_delete', requirements: ['teacherClasseId' => '\d+', 'quizId' => '\d+', 'questionId' => '\d+'], methods: ['POST'])]
    public function deleteQuestion(Request $request, int $teacherClasseId, int $quizId, int $questionId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz || !$this->verifyQuizBelongsToTeacher($quiz, $teacherClasse)) {
            $this->addFlash('error', 'Quiz not found.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        if (!$this->isCsrfTokenValid('delete_question'.$questionId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_quiz_edit', [
                'teacherClasseId' => $teacherClasseId,
                'quizId' => $quizId
            ]);
        }

        $question = $this->entityManager->getRepository(Question::class)->find($questionId);
        if ($question && $question->getQuiz()->getId() === $quiz->getId()) {
            $this->entityManager->remove($question);
            $this->entityManager->flush();
            $this->addFlash('success', 'Question deleted successfully!');
        }

        return $this->redirectToRoute('app_teacher_quiz_edit', [
            'teacherClasseId' => $teacherClasseId,
            'quizId' => $quizId
        ]);
    }

    #[Route('/{teacherClasseId}/quiz/{quizId}/results', name: 'app_teacher_quiz_results', requirements: ['teacherClasseId' => '\d+', 'quizId' => '\d+'], methods: ['GET'])]
    public function viewResults(int $teacherClasseId, int $quizId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz || !$this->verifyQuizBelongsToTeacher($quiz, $teacherClasse)) {
            $this->addFlash('error', 'Quiz not found.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        // Get all user answers for this quiz
        $userAnswers = $this->userAnswerRepository->findBy(
            ['quiz' => $quiz],
            ['completedAt' => 'DESC']
        );

        return $this->render('Gestion_Program/teacher_quiz/results.html.twig', [
            'teacherClasse' => $teacherClasse,
            'quiz' => $quiz,
            'userAnswers' => $userAnswers,
        ]);
    }

    #[Route('/{teacherClasseId}/quiz/{quizId}/delete', name: 'app_teacher_quiz_delete', requirements: ['teacherClasseId' => '\d+', 'quizId' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $teacherClasseId, int $quizId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $teacherClasse = $this->teacherClasseRepository->find($teacherClasseId);
        
        if (!$teacherClasse || $teacherClasse->getTeacher()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Unauthorized action.');
            return $this->redirectToRoute('app_teacher_my_classes');
        }

        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz || !$this->verifyQuizBelongsToTeacher($quiz, $teacherClasse)) {
            $this->addFlash('error', 'Quiz not found.');
            return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
        }

        if (!$this->isCsrfTokenValid('delete_quiz'.$quizId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_teacher_quiz_edit', [
                'teacherClasseId' => $teacherClasseId,
                'quizId' => $quizId
            ]);
        }

        $quizTitle = $quiz->getTitle();
        $contenu = $quiz->getContenu();

        // Remove the quiz (will cascade to questions and choices)
        $this->entityManager->remove($quiz);
        
        // Also remove the contenu
        if ($contenu) {
            $this->entityManager->remove($contenu);
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Quiz "%s" deleted successfully.', $quizTitle));
        return $this->redirectToRoute('app_teacher_classe_show', ['id' => $teacherClasseId]);
    }

    private function verifyQuizBelongsToTeacher(Quiz $quiz, $teacherClasse): bool
    {
        $module = $teacherClasse->getModule();
        if (!$module) {
            return false;
        }

        $contenu = $quiz->getContenu();
        if (!$contenu) {
            return false;
        }

        foreach ($module->getCourses() as $mc) {
            $course = $mc->getCourse();
            if ($course) {
                foreach ($course->getContenus() as $cc) {
                    if ($cc->getContenu() && $cc->getContenu()->getId() === $contenu->getId()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
