<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\GradeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI-powered recommendation service that analyzes student performance
 * and suggests online courses for improvement
 */
class AIRecommendationService
{
    private const PASSING_THRESHOLD = 10.0; // Out of 20
    private const EXCELLENT_THRESHOLD = 15.0;
    private const GOOD_THRESHOLD = 12.0;
    
    private array $courseRecommendations = [
        'Mathématiques' => [
            'Khan Academy - Mathematics' => 'https://www.khanacademy.org/math',
            'OpenClassrooms - Math Review' => 'https://openclassrooms.com/fr/courses/6417031-perfectionnez-vous-en-mathematiques',
            'Coursera - Calculus' => 'https://www.coursera.org/learn/calculus1',
        ],
        'Physique' => [
            'Khan Academy - Physics' => 'https://www.khanacademy.org/science/physics',
            'OpenClassrooms - Physics Basics' => 'https://openclassrooms.com/fr/courses/3434061-comprenez-les-bases-de-la-physique',
            'MIT OpenCourseWare - Physics' => 'https://ocw.mit.edu/courses/physics/',
        ],
        'Informatique' => [
            'freeCodeCamp' => 'https://www.freecodecamp.org/',
            'OpenClassrooms - Web Development' => 'https://openclassrooms.com/fr/paths/185-developpeur-web',
            'Codecademy' => 'https://www.codecademy.com/',
        ],
        'Anglais' => [
            'Duolingo' => 'https://www.duolingo.com/course/en/fr/Apprendre-anglais',
            'BBC Learning English' => 'https://www.bbc.co.uk/learningenglish/',
            'Cambridge English Online' => 'https://www.cambridgeenglish.org/learning-english/',
        ],
        'Français' => [
            'Projet Voltaire' => 'https://www.projet-voltaire.fr/',
            'OpenClassrooms - French Language' => 'https://openclassrooms.com/fr/courses/5871026-perfectionnez-votre-orthographe',
            'TV5MONDE' => 'https://apprendre.tv5monde.com/fr',
        ],
        'Histoire' => [
            'Khan Academy - World History' => 'https://www.khanacademy.org/humanities/world-history',
            'OpenClassrooms - Contemporary History' => 'https://openclassrooms.com/fr/courses/4810221-initiez-vous-a-l-histoire-contemporaine',
            'Coursera - World History' => 'https://www.coursera.org/courses?query=world%20history',
        ],
        'Chimie' => [
            'Khan Academy - Chemistry' => 'https://www.khanacademy.org/science/chemistry',
            'OpenClassrooms - Chemistry Basics' => 'https://openclassrooms.com/fr/courses/5641851-decouvrez-les-bases-de-la-chimie',
            'Coursera - Chemistry' => 'https://www.coursera.org/courses?query=chemistry',
        ],
        'Économie' => [
            'Khan Academy - Economics' => 'https://www.khanacademy.org/economics-finance-domain',
            'OpenClassrooms - Economics Fundamentals' => 'https://openclassrooms.com/fr/courses/5419246-decouvrez-les-fondamentaux-de-l-economie',
            'Coursera - Economics' => 'https://www.coursera.org/courses?query=economics',
        ],
    ];

    private array $youtubeVideos = [
        'Mathématiques' => [
            ['title' => 'Complete Math Lectures – High School & Beyond', 'channel' => 'Yvan Monka', 'url' => 'https://www.youtube.com/@YvanMonka', 'description' => 'Clear and structured video math lessons'],
            ['title' => 'Essence of Linear Algebra', 'channel' => '3Blue1Brown', 'url' => 'https://www.youtube.com/playlist?list=PLZHQObOWTQDPD3MizzM2xVFitgF8hE_ab', 'description' => 'Intuitive visualisation of linear algebra'],
            ['title' => 'Mathematics for Beginners', 'channel' => 'Les Maths Simplement', 'url' => 'https://www.youtube.com/@LesMathsSimplement', 'description' => 'Catch-up lessons and corrected exercises'],
            ['title' => 'Khan Academy Math', 'channel' => 'Khan Academy', 'url' => 'https://www.youtube.com/@KhanAcademyFrance', 'description' => 'Interactive exercises and progressive courses'],
        ],
        'Physique' => [
            ['title' => 'Physics – Experiments and Explanations', 'channel' => 'e-penser', 'url' => 'https://www.youtube.com/@Epenser1', 'description' => 'High-level science popularisation'],
            ['title' => 'Quantum Physics and Relativity', 'channel' => 'Science Étonnante', 'url' => 'https://www.youtube.com/@ScienceEtonnante', 'description' => 'Physics explained simply'],
            ['title' => 'MIT Physics Lectures (Walter Lewin)', 'channel' => 'For The Love of Physics', 'url' => 'https://www.youtube.com/@lecturesbywalterlewin.they9516', 'description' => 'Classical mechanics and electricity'],
            ['title' => 'Physics & Chemistry Lessons', 'channel' => 'Maxime Danger', 'url' => 'https://www.youtube.com/@maximedanger', 'description' => 'Lessons and exercises'],
        ],
        'Informatique' => [
            ['title' => 'Learn Python Programming', 'channel' => 'Graven', 'url' => 'https://www.youtube.com/@Gravenilvectuto', 'description' => 'Programming tutorials in French'],
            ['title' => 'CS50 – Introduction to Computer Science', 'channel' => 'CS50', 'url' => 'https://www.youtube.com/@cs50', 'description' => 'The best intro CS course for beginners'],
            ['title' => '100 Seconds of Code', 'channel' => 'Fireship', 'url' => 'https://www.youtube.com/@Fireship', 'description' => 'Web technologies in short-form videos'],
            ['title' => 'Algorithms & Data Structures', 'channel' => 'freeCodeCamp.org', 'url' => 'https://www.youtube.com/watch?v=8hly31xKli0', 'description' => 'Essential algorithms for CS students'],
        ],
        'Anglais' => [
            ['title' => 'English Grammar Lessons', 'channel' => 'EnglishClass101', 'url' => 'https://www.youtube.com/@EnglishClass101', 'description' => 'Grammar and English vocabulary'],
            ['title' => 'BBC Learning English', 'channel' => 'BBC Learning English', 'url' => 'https://www.youtube.com/@bbclearningenglish', 'description' => 'Authentic English by the BBC'],
            ['title' => 'American English for French Speakers', 'channel' => 'Speak English With Vanessa', 'url' => 'https://www.youtube.com/@SpeakEnglishWithVanessa', 'description' => 'Conversation and pronunciation'],
            ['title' => 'IELTS & TOEFL Preparation', 'channel' => 'E2 Language', 'url' => 'https://www.youtube.com/@E2Language', 'description' => 'Certification exam preparation'],
        ],
        'Français' => [
            ['title' => 'Spelling and Grammar Rules', 'channel' => 'Orthonet', 'url' => 'https://www.youtube.com/@orthonet', 'description' => 'French spelling rules explained'],
            ['title' => 'French Literature Explained', 'channel' => 'Belin Éducation', 'url' => 'https://www.youtube.com/@belineducation', 'description' => 'Analysis of set works'],
            ['title' => 'Essay & Commentary Methodology', 'channel' => 'Cyrus North', 'url' => 'https://www.youtube.com/@CyrusNorth', 'description' => 'Methodology for written exams'],
        ],
        'Histoire' => [
            ['title' => 'History – Summaries and Analyses', 'channel' => 'Nota Bene', 'url' => 'https://www.youtube.com/@NotaBeneVideos', 'description' => 'History told differently'],
            ['title' => 'History of the World in 6 Minutes', 'channel' => 'Kurzgesagt', 'url' => 'https://www.youtube.com/@kurzgesagt', 'description' => 'Visual and scientific explanations'],
            ['title' => 'Crash Course History', 'channel' => 'CrashCourse', 'url' => 'https://www.youtube.com/playlist?list=PLybg94GvOJ9E9BcCOoRRQ0lGgDI_KUqT8', 'description' => 'World history in themed series'],
        ],
        'Chimie' => [
            ['title' => 'Organic Chemistry Explained', 'channel' => 'Professor Dave Explains', 'url' => 'https://www.youtube.com/@ProfessorDaveExplains', 'description' => 'Organic and general chemistry courses'],
            ['title' => 'Chemistry – Lessons and Exercises', 'channel' => 'Khan Academy', 'url' => 'https://www.youtube.com/@KhanAcademyFrance', 'description' => 'Atoms, reactions, electrochemistry'],
            ['title' => 'Chemistry Made Simple', 'channel' => "C'est pas sorcier", 'url' => 'https://www.youtube.com/@cestpassorcier', 'description' => 'Day-to-day chemistry popularised'],
        ],
        'Économie' => [
            ['title' => 'Economics for Beginners', 'channel' => 'Heu?reka', 'url' => 'https://www.youtube.com/@heureka9', 'description' => 'Macro and microeconomics explained'],
            ['title' => 'Microeconomics – Crash Course', 'channel' => 'CrashCourse', 'url' => 'https://www.youtube.com/playlist?list=PL8dPuuaLjXtPNZwz5_o_5uirJ8gQXnhEO', 'description' => 'Microeconomics fundamentals'],
            ['title' => 'Understanding the World Economy', 'channel' => 'Le Dessous des Cartes', 'url' => 'https://www.youtube.com/@ledessousdescartes', 'description' => 'Geopolitics and the global economy'],
        ],
    ];

    private array $defaultYoutubeVideos = [
        ['title' => 'How to Study Effectively', 'channel' => 'Thomas Frank', 'url' => 'https://www.youtube.com/@ThomasFrankExplains', 'description' => 'Memory techniques and productivity'],
        ['title' => 'The Feynman Technique – Learn Anything', 'channel' => 'Ali Abdaal', 'url' => 'https://www.youtube.com/watch?v=_f-qkGJBPts', 'description' => 'The most effective learning technique'],
        ['title' => 'Khan Academy', 'channel' => 'Khan Academy', 'url' => 'https://www.youtube.com/@khanacademy', 'description' => 'Courses across all subjects'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GradeRepository $gradeRepository,
        private HttpClientInterface $httpClient,
        private string $groqApiKey = '',
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Generate personalized AI recommendations for a student
     *
     * @param User $student The student to analyze
     * @return array Array of recommendations with priority levels
     */
    public function generateRecommendations(User $student): array
    {
        $grades = $this->gradeRepository->createQueryBuilder('g')
            ->join('g.assessment', 'a')
            ->leftJoin('a.course', 'c')
            ->where('g.student = :student')
            ->setParameter('student', $student)
            ->getQuery()
            ->getResult();

        if (empty($grades)) {
            return [];
        }

        // Group grades by course
        $courseGrades = [];
        foreach ($grades as $grade) {
            $course = $grade->getAssessment()->getCourse();
            $courseName = $course ? $course->getTitle() : $grade->getAssessment()->getTitle();
            if (!isset($courseGrades[$courseName])) {
                $courseGrades[$courseName] = [];
            }
            $courseGrades[$courseName][] = $grade;
        }

        // Calculate averages and generate recommendations
        $recommendations = [];
        foreach ($courseGrades as $courseName => $grades) {
            $average = $this->calculateAverage($grades);

            // Only recommend if performance is below good threshold
            if ($average < self::GOOD_THRESHOLD) {
                $aiData = $this->callGroqFullRecommendation(
                    $courseName,
                    $average,
                    count($grades),
                    $student->getName() ?? 'l’étudiant'
                );

                $recommendations[] = [
                    'courseName'         => $courseName,
                    'average'            => round($average, 2),
                    'status'             => $this->getStatusLabel($average),
                    'priority'           => $this->calculatePriority($average),
                    'recommendedCourses' => $aiData['courses'],
                    'youtubeVideos'      => $aiData['youtube'],
                    'aiInsight'          => $aiData['insight'],
                ];
            }
        }

        // Sort by priority (HIGH first)
        usort($recommendations, function($a, $b) {
            $priorityOrder = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
            return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
        });

        return $recommendations;
    }

    /**
     * Calculate semester results for a student
     *
     * @param User $student The student to analyze
     * @return array Complete semester results with statistics
     */
    public function calculateSemesterResults(User $student): array
    {
        $grades = $this->gradeRepository->createQueryBuilder('g')
            ->join('g.assessment', 'a')
            ->leftJoin('a.course', 'c')
            ->where('g.student = :student')
            ->setParameter('student', $student)
            ->getQuery()
            ->getResult();

        if (empty($grades)) {
            return [
                'overallAverage' => 0,
                'passedCourses' => 0,
                'failedCourses' => 0,
                'courses' => [],
            ];
        }

        // Group grades by course
        $courseGrades = [];
        foreach ($grades as $grade) {
            $course = $grade->getAssessment()->getCourse();
            $courseName = $course ? $course->getTitle() : $grade->getAssessment()->getTitle();
            if (!isset($courseGrades[$courseName])) {
                $courseGrades[$courseName] = [];
            }
            $courseGrades[$courseName][] = $grade;
        }

        // Calculate course statistics
        $courses = [];
        $totalAverage = 0;
        $passedCount = 0;
        $failedCount = 0;

        foreach ($courseGrades as $courseName => $grades) {
            $average = $this->calculateAverage($grades);
            $isPassed = $average >= self::PASSING_THRESHOLD;
            
            if ($isPassed) {
                $passedCount++;
            } else {
                $failedCount++;
            }

            $totalAverage += $average;

            $gradeDetails = [];
            foreach ($grades as $grade) {
                $gradeDetails[] = [
                    'assessment' => $grade->getAssessment()->getTitle(),
                    'score' => $grade->getScore(),
                    'maxScore' => 20, // Default for French system
                    'percentage' => ($grade->getScore() / 20) * 100,
                ];
            }

            $courses[$courseName] = [
                'name' => $courseName,
                'average' => round($average, 2),
                'status' => $isPassed ? 'Passed' : 'Failed',
                'grades' => $gradeDetails,
            ];
        }

        return [
            'overallAverage' => count($courseGrades) > 0 ? round($totalAverage / count($courseGrades), 2) : 0,
            'passedCourses' => $passedCount,
            'failedCourses' => $failedCount,
            'courses' => $courses,
        ];
    }

    /**
     * Calculate average score from grades (French system: /20)
     */
    private function calculateAverage(array $grades): float
    {
        if (empty($grades)) {
            return 0;
        }

        $total = 0;
        foreach ($grades as $grade) {
            $total += $grade->getScore();
        }

        return $total / count($grades);
    }

    /**
     * Get status label based on average
     */
    private function getStatusLabel(float $average): string
    {
        if ($average >= self::EXCELLENT_THRESHOLD) {
            return 'Excellent';
        } elseif ($average >= self::GOOD_THRESHOLD) {
            return 'Good';
        } elseif ($average >= self::PASSING_THRESHOLD) {
            return 'Satisfactory';
        } else {
            return 'Insufficient';
        }
    }

    /**
     * Calculate priority level for improvement
     */
    private function calculatePriority(float $average): string
    {
        if ($average < 8) {
            return 'HIGH';
        } elseif ($average < self::PASSING_THRESHOLD) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }

    /**
     * Get recommended YouTube videos based on subject.
     * Uses bidirectional keyword matching; falls back to subject-specific YouTube search.
     */
    private function getYoutubeVideos(string $courseName): array
    {
        foreach ($this->youtubeVideos as $subject => $videos) {
            if (
                stripos($courseName, $subject) !== false ||
                stripos($subject, $courseName) !== false
            ) {
                return $videos;
            }
        }

        // Dynamic fallback: YouTube search for the actual course name
        $query = urlencode($courseName . ' cours tutoriel');
        return [
            [
                'title'       => 'Recherche YouTube – ' . $courseName,
                'channel'     => 'YouTube',
                'url'         => 'https://www.youtube.com/results?search_query=' . $query,
                'description' => 'Video lessons and tutorials for ' . $courseName,
            ],
            [
                'title'       => 'Khan Academy – ' . $courseName,
                'channel'     => 'Khan Academy',
                'url'         => 'https://www.youtube.com/results?search_query=' . urlencode('khan academy ' . $courseName),
                'description' => 'Leçons Khan Academy sur ' . $courseName,
            ],
            [
                'title'       => 'OpenClassrooms – ' . $courseName,
                'channel'     => 'OpenClassrooms',
                'url'         => 'https://www.youtube.com/results?search_query=' . urlencode('openclassrooms ' . $courseName),
                'description' => 'OpenClassrooms content for ' . $courseName,
            ],
        ];
    }

    /**
     * Get recommended online courses based on subject.
     * Uses bidirectional keyword matching; falls back to subject-specific search URLs.
     */
    private function getRecommendedCourses(string $courseName): array
    {
        foreach ($this->courseRecommendations as $subject => $courses) {
            if (
                stripos($courseName, $subject) !== false ||
                stripos($subject, $courseName) !== false
            ) {
                return $courses;
            }
        }

        // Dynamic fallback: search URLs built from the actual course name
        $query = urlencode($courseName);
        return [
            'Coursera – ' . $courseName => 'https://www.coursera.org/search?query=' . $query,
            'Khan Academy – ' . $courseName => 'https://www.khanacademy.org/search?page_search_query=' . $query,
            'OpenClassrooms – ' . $courseName => 'https://openclassrooms.com/fr/search?q=' . $query,
            'MIT OpenCourseWare – ' . $courseName => 'https://ocw.mit.edu/search/?q=' . $query,
        ];
    }

    /**
     * Single Groq API call that generates insight + course recommendations + YouTube videos
     * for a specific subject based on real grade data. Falls back to static data on failure.
     */
    private function callGroqFullRecommendation(
        string $courseName,
        float $average,
        int $gradeCount,
        string $studentName
    ): array {
        $fallback = [
            'insight' => $this->generateAIInsight($average, $gradeCount),
            'courses' => $this->getRecommendedCourses($courseName),
            'youtube' => $this->getYoutubeVideos($courseName),
        ];

        if (empty($this->groqApiKey)) {
            return $fallback;
        }

        $prompt = sprintf(
            'Tu es un assistant pédagogique expert. '
            . 'Réponds UNIQUEMENT en JSON valide, sans texte avant ou après. '
            . 'L’étudiant "%s" a obtenu %.2f/20 en "%s" (%d évaluation(s)). '
            . 'Génère une réponse personnalisée avec ce format exact: '
            . '{ '
            . '  "insight": "conseil personnalisé en 2-3 phrases, en français, encourageant", '
            . '  "courses": [ '
            . '    {"name": "nom du cours en ligne", "url": "url valide", "description": "courte description"}, '
            . '    ... (3 à 4 cours) '
            . '  ], '
            . '  "youtube": [ '
            . '    {"title": "titre de la vidéo ou chaîne", "channel": "nom de la chaîne", "url": "url youtube valide", "description": "courte description"}, '
            . '    ... (3 à 4 vidéos) '
            . '  ] '
            . '} '
            . 'Les ressources doivent être spécifiquement adaptées à la matière "%s". '
            . 'Utilise des liens réels et populaires (Khan Academy, Coursera, OpenClassrooms, YouTube, etc.).',
            $studentName,
            $average,
            $courseName,
            $gradeCount,
            $courseName
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'llama-3.1-8b-instant',
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'Tu es un assistant pédagogique. Tu réponds toujours en JSON valide uniquement, sans markdown, sans explication.',
                        ],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature'    => 0.6,
                    'max_tokens'     => 800,
                    'response_format' => ['type' => 'json_object'],
                ],
                'timeout' => 10,
            ]);

            $data    = $response->toArray();
            $content = trim($data['choices'][0]['message']['content'] ?? '');
            $parsed  = json_decode($content, true);

            if (!is_array($parsed)) {
                return $fallback;
            }

            // Build courses array (key => url) to match template format
            $courses = [];
            foreach ($parsed['courses'] ?? [] as $c) {
                if (!empty($c['name']) && !empty($c['url'])) {
                    $courses[$c['name'] . (!empty($c['description']) ? ' — ' . $c['description'] : '')] = $c['url'];
                }
            }

            // Build youtube array
            $youtube = [];
            foreach ($parsed['youtube'] ?? [] as $v) {
                if (!empty($v['title']) && !empty($v['url'])) {
                    $youtube[] = [
                        'title'       => $v['title'],
                        'channel'     => $v['channel'] ?? '',
                        'url'         => $v['url'],
                        'description' => $v['description'] ?? '',
                    ];
                }
            }

            return [
                'insight' => !empty($parsed['insight']) ? $parsed['insight'] : $fallback['insight'],
                'courses' => !empty($courses) ? $courses : $fallback['courses'],
                'youtube' => !empty($youtube) ? $youtube : $fallback['youtube'],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Groq API error for course "' . $courseName . '": ' . $e->getMessage());
            return $fallback;
        }
    }

    /**
     * Call Groq AI API to generate a personalised insight for the student.
     * Falls back to the static generator if the API is unavailable.
     */
    private function callGroqInsight(string $courseName, float $average, int $gradeCount, string $studentName): string
    {
        if (empty($this->groqApiKey)) {
            return $this->generateAIInsight($average, $gradeCount);
        }

        $prompt = sprintf(
            'Tu es un assistant pédagogique bienveillant. '
            . 'Réponds en français en 2-3 phrases concises et encourageantes. '
            . 'L’étudiant %s a obtenu une moyenne de %.2f/20 en %s (%d évaluation(s)). '
            . 'Donne-lui un conseil personnalisé et motivant pour améliorer ses résultats dans cette matière.',
            $studentName,
            $average,
            $courseName,
            $gradeCount
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => 'llama-3.1-8b-instant',
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 150,
                ],
                'timeout' => 8,
            ]);

            $data = $response->toArray();
            return trim($data['choices'][0]['message']['content'] ?? '') ?: $this->generateAIInsight($average, $gradeCount);
        } catch (\Throwable) {
            return $this->generateAIInsight($average, $gradeCount);
        }
    }

    /**
     * Generate AI-powered insight based on performance (static fallback)
     */
    private function generateAIInsight(float $average, int $gradeCount): string
    {
        $insights = [];

        if ($average < 8) {
            $insights[] = "Your performance requires immediate attention.";
            $insights[] = "I recommend dedicating at least 2 hours per day to this course.";
        } elseif ($average < self::PASSING_THRESHOLD) {
            $insights[] = "You are close to the passing threshold.";
            $insights[] = "With a little more effort, you can easily improve your results.";
        } else {
            $insights[] = "Your performance is adequate but can be improved.";
            $insights[] = "Aim for excellence by working on the identified weak points.";
        }

        if ($gradeCount < 3) {
            $insights[] = "More assessments are needed for a complete analysis.";
        }

        return implode(' ', $insights);
    }
}
