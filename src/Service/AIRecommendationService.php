<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\GradeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * AI-powered recommendation service that analyzes student performance.
 * Uses Groq (LLaMA 3.3 70B) for real personalized recommendations.
 * Falls back to rule-based logic when Groq is unavailable.
 */
class AIRecommendationService
{
    private const PASSING_THRESHOLD   = 10.0;
    private const EXCELLENT_THRESHOLD = 15.0;
    private const GOOD_THRESHOLD      = 12.0;

    // Fallback static recommendations (used only when Groq is unavailable)
    private array $courseRecommendations = [
        'Mathématiques' => [
            'Khan Academy - Mathématiques' => 'https://www.khanacademy.org/math',
            'OpenClassrooms - Réviser ses maths' => 'https://openclassrooms.com/fr/courses/6417031-perfectionnez-vous-en-mathematiques',
            'Coursera - Calculus' => 'https://www.coursera.org/learn/calculus1',
        ],
        'Physique' => [
            'Khan Academy - Physique' => 'https://www.khanacademy.org/science/physics',
            'OpenClassrooms - Physique' => 'https://openclassrooms.com/fr/courses/3434061-comprenez-les-bases-de-la-physique',
            'MIT OpenCourseWare - Physics' => 'https://ocw.mit.edu/courses/physics/',
        ],
        'Informatique' => [
            'freeCodeCamp' => 'https://www.freecodecamp.org/',
            'OpenClassrooms - Programmation' => 'https://openclassrooms.com/fr/paths/185-developpeur-web',
            'Codecademy' => 'https://www.codecademy.com/',
        ],
        'Anglais' => [
            'Duolingo' => 'https://www.duolingo.com/course/en/fr/Apprendre-anglais',
            'BBC Learning English' => 'https://www.bbc.co.uk/learningenglish/',
            'Cambridge English Online' => 'https://www.cambridgeenglish.org/learning-english/',
        ],
        'Français' => [
            'Projet Voltaire' => 'https://www.projet-voltaire.fr/',
            'OpenClassrooms - Français' => 'https://openclassrooms.com/fr/courses/5871026-perfectionnez-votre-orthographe',
            'TV5MONDE' => 'https://apprendre.tv5monde.com/fr',
        ],
        'Histoire' => [
            'Khan Academy - Histoire' => 'https://www.khanacademy.org/humanities/world-history',
            'OpenClassrooms - Histoire' => 'https://openclassrooms.com/fr/courses/4810221-initiez-vous-a-l-histoire-contemporaine',
            'Coursera - World History' => 'https://www.coursera.org/courses?query=world%20history',
        ],
        'Chimie' => [
            'Khan Academy - Chimie' => 'https://www.khanacademy.org/science/chemistry',
            'OpenClassrooms - Chimie' => 'https://openclassrooms.com/fr/courses/5641851-decouvrez-les-bases-de-la-chimie',
            'Coursera - Chemistry' => 'https://www.coursera.org/courses?query=chemistry',
        ],
        'Économie' => [
            'Khan Academy - Économie' => 'https://www.khanacademy.org/economics-finance-domain',
            'OpenClassrooms - Économie' => 'https://openclassrooms.com/fr/courses/5419246-decouvrez-les-fondamentaux-de-l-economie',
            'Coursera - Economics' => 'https://www.coursera.org/courses?query=economics',
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GradeRepository        $gradeRepository,
        private GroqApiService         $groq
    ) {}

    /**
     * Generate personalized AI recommendations using Groq (LLaMA 3.3 70B).
     * Falls back to rule-based logic if Groq is unavailable.
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

        // Build per-course summary
        $courseGrades = [];
        foreach ($grades as $grade) {
            $assessment = $grade->getAssessment();
            $course     = $assessment->getCourse();
            // Use course title → assessment title → type label as fallback (never "Cours sans titre")
            if ($course && $course->getTitle()) {
                $courseName = $course->getTitle();
            } elseif ($assessment->getTitle()) {
                $courseName = $assessment->getTitle();
            } else {
                $type = $assessment->getType();
                $courseName = $type ? $type->value : 'Module sans titre';
            }
            if (!isset($courseGrades[$courseName])) {
                $courseGrades[$courseName] = [];
            }
            $courseGrades[$courseName][] = $grade;
        }

        $courseSummaries = [];
        foreach ($courseGrades as $courseName => $cGrades) {
            $avg = $this->calculateAverage($cGrades);
            $courseSummaries[] = [
                'name'    => $courseName,
                'average' => round($avg, 2),
                'grades'  => count($cGrades),
                'status'  => $this->getStatusLabel($avg),
                'priority'=> $this->calculatePriority($avg),
            ];
        }

        // Filter only struggling courses
        $struggling = array_filter($courseSummaries, fn($c) => $c['average'] < self::GOOD_THRESHOLD);

        if (empty($struggling)) {
            return [];
        }

        // Try Groq first
        if ($this->groq->isAvailable()) {
            $groqResults = $this->fetchGroqRecommendations(array_values($struggling));
            if (!empty($groqResults)) {
                return $groqResults;
            }
        }

        // Fallback: rule-based
        $recommendations = [];
        foreach ($struggling as $course) {
            $recommendations[] = [
                'courseName'         => $course['name'],
                'average'            => $course['average'],
                'status'             => $course['status'],
                'priority'           => $course['priority'],
                'aiInsight'          => $this->generateAIInsight($course['average'], $course['grades']),
                'platforms'          => $this->buildFallbackPlatforms($course['name']),
                'youtubeSearches'    => $this->buildYoutubeSearches($course['name']),
                'recommendedCourses' => $this->getRecommendedCourses($course['name']), // kept for back-compat
            ];
        }

        usort($recommendations, function ($a, $b) {
            $order = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
            return $order[$a['priority']] <=> $order[$b['priority']];
        });

        return $recommendations;
    }

    // ─────────────────────────────────────────────────────────────
    // GROQ integration
    // ─────────────────────────────────────────────────────────────

    private function fetchGroqRecommendations(array $strugglingCourses): array
    {
        $courseList = implode("\n", array_map(
            fn($c) => "- {$c['name']}: {$c['average']}/20 ({$c['status']})",
            $strugglingCourses
        ));

        $system = <<<SYS
You are an expert academic advisor for university students.
You must respond ONLY with a valid JSON array — no markdown, no extra text.
SYS;

        $user = <<<USR
A university student is struggling in the following modules:
{$courseList}

For EACH module, provide a JSON object with this EXACT structure:
{
  "courseName": "exact module name from the list",
  "insight": "2-3 sentence personalized analysis of why students struggle in this module and the key areas to focus on",
  "platforms": [
    {"name": "Platform Name", "url": "https://...", "icon": "one of: youtube/khan/coursera/udemy/openclassrooms/edx/mit/web", "description": "what this platform offers for this specific topic"},
    ... (3-5 platforms)
  ],
  "youtubeSearches": [
    {"query": "exact youtube search query for this topic", "label": "short label describing what this search covers"},
    ... (3-4 searches)
  ]
}

Return a JSON ARRAY: [ {...}, {...} ]
Choose platform URLs that are specific to the subject (not generic homepages).
YouTube queries must be specific, targeted, and in the student's language (French if course name is French).
USR;

        $raw = $this->groq->chat($system, $user, 0.5);
        if (!$raw) {
            return [];
        }

        $parsed = $this->groq->parseJson($raw);
        if (!is_array($parsed) || empty($parsed)) {
            return [];
        }

        // Merge Groq data with calculated stats
        $recommendations = [];
        foreach ($parsed as $item) {
            $matchedCourse = null;
            foreach ($strugglingCourses as $c) {
                if (strtolower($c['name']) === strtolower($item['courseName'] ?? '')) {
                    $matchedCourse = $c;
                    break;
                }
                // Fuzzy: check if one contains the other
                if (!empty($item['courseName']) &&
                    (str_contains(strtolower($c['name']), strtolower($item['courseName'])) ||
                    str_contains(strtolower($item['courseName']), strtolower($c['name'])))) {
                    $matchedCourse = $c;
                    break;
                }
            }

            if (!$matchedCourse) {
                // Still include it with defaults
                $matchedCourse = ['average' => 0, 'status' => 'Insuffisant', 'priority' => 'HIGH', 'grades' => 0];
            }

            // Build YouTube URLs from search queries
            $youtubeSearches = [];
            foreach (($item['youtubeSearches'] ?? []) as $yt) {
                $youtubeSearches[] = [
                    'label' => $yt['label'] ?? $yt['query'],
                    'query' => $yt['query'],
                    'url'   => 'https://www.youtube.com/results?search_query=' . urlencode($yt['query']),
                ];
            }

            $recommendations[] = [
                'courseName'         => $item['courseName'] ?? $matchedCourse['name'] ?? 'Inconnu',
                'average'            => $matchedCourse['average'],
                'status'             => $matchedCourse['status'],
                'priority'           => $matchedCourse['priority'],
                'aiInsight'          => $item['insight'] ?? '',
                'platforms'          => $item['platforms'] ?? [],
                'youtubeSearches'    => $youtubeSearches,
                'recommendedCourses' => [], // replaced by platforms
                'fromGroq'           => true,
            ];
        }

        // Sort HIGH → MEDIUM → LOW
        usort($recommendations, function ($a, $b) {
            $order = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
            return ($order[$a['priority']] ?? 3) <=> ($order[$b['priority']] ?? 3);
        });

        return $recommendations;
    }

    // ─────────────────────────────────────────────────────────────
    // Fallback helpers (used when Groq unavailable)
    // ─────────────────────────────────────────────────────────────

    private function buildFallbackPlatforms(string $courseName): array
    {
        $map = [
            'math'      => [
                ['name' => 'Khan Academy', 'url' => 'https://fr.khanacademy.org/math', 'icon' => 'khan', 'description' => 'Cours vidéos interactifs de mathématiques'],
                ['name' => 'Coursera - Calculus', 'url' => 'https://www.coursera.org/learn/calculus1', 'icon' => 'coursera', 'description' => 'Cours de calcul de l\'université Michigan'],
                ['name' => 'MIT OpenCourseWare', 'url' => 'https://ocw.mit.edu/courses/mathematics/', 'icon' => 'mit', 'description' => 'Cours gratuits du MIT'],
            ],
            'physique'  => [
                ['name' => 'Khan Academy Physique', 'url' => 'https://fr.khanacademy.org/science/physics', 'icon' => 'khan', 'description' => 'Physique niveau lycée/université'],
                ['name' => 'MIT Physics', 'url' => 'https://ocw.mit.edu/courses/physics/', 'icon' => 'mit', 'description' => 'Cours de physique du MIT'],
            ],
            'info'      => [
                ['name' => 'OpenClassrooms', 'url' => 'https://openclassrooms.com/fr/paths/185-developpeur-web', 'icon' => 'openclassrooms', 'description' => 'Formation développeur web francophone'],
                ['name' => 'freeCodeCamp', 'url' => 'https://www.freecodecamp.org/', 'icon' => 'web', 'description' => 'Apprentissage gratuit de la programmation'],
                ['name' => 'Codecademy', 'url' => 'https://www.codecademy.com/', 'icon' => 'web', 'description' => 'Cours interactifs de programmation'],
            ],
        ];
        $lower = strtolower($courseName);
        foreach ($map as $key => $platforms) {
            if (str_contains($lower, $key)) return $platforms;
        }
        return [
            ['name' => 'Khan Academy', 'url' => 'https://fr.khanacademy.org/', 'icon' => 'khan', 'description' => 'Cours gratuits tous niveaux'],
            ['name' => 'OpenClassrooms', 'url' => 'https://openclassrooms.com/fr/', 'icon' => 'openclassrooms', 'description' => 'Formations en ligne francophones'],
            ['name' => 'Coursera', 'url' => 'https://www.coursera.org/', 'icon' => 'coursera', 'description' => 'Cours universitaires en ligne'],
        ];
    }

    private function buildYoutubeSearches(string $courseName): array
    {
        $query = urlencode("cours $courseName débutant explication");
        return [
            ['label' => "Cours $courseName", 'query' => "cours $courseName explication", 'url' => "https://www.youtube.com/results?search_query=" . $query],
            ['label' => "Exercices résolus $courseName", 'query' => "exercices résolus $courseName", 'url' => "https://www.youtube.com/results?search_query=" . urlencode("exercices résolus $courseName")],
        ];
    }

    /**
     * Calculate semester results for a student
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
            $courseName = $course ? $course->getTitle() : 'Cours sans titre';
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
                'averagePercentage' => round(($average / 20) * 100, 2),
                'status' => $isPassed ? 'Réussi' : 'Échoué',
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
            return 'Bien';
        } elseif ($average >= self::PASSING_THRESHOLD) {
            return 'Passable';
        } else {
            return 'Insuffisant';
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
     * Get recommended online courses based on subject
     */
    private function getRecommendedCourses(string $courseName): array
    {
        // Try to match course name with available recommendations
        foreach ($this->courseRecommendations as $subject => $courses) {
            if (stripos($courseName, $subject) !== false) {
                return $courses;
            }
        }

        // Default recommendations
        return [
            'Khan Academy' => 'https://www.khanacademy.org/',
            'OpenClassrooms' => 'https://openclassrooms.com/fr/',
            'Coursera' => 'https://www.coursera.org/',
        ];
    }

    /**
     * Generate AI-powered insight based on performance
     */
    private function generateAIInsight(float $average, int $gradeCount): string
    {
        $insights = [];

        if ($average < 8) {
            $insights[] = "Votre performance nécessite une attention immédiate.";
            $insights[] = "Je recommande de consacrer au moins 2 heures par jour à ce cours.";
        } elseif ($average < self::PASSING_THRESHOLD) {
            $insights[] = "Vous êtes proche du seuil de réussite.";
            $insights[] = "Avec un peu plus d'efforts, vous pouvez facilement améliorer vos résultats.";
        } else {
            $insights[] = "Votre performance est correcte mais peut être améliorée.";
            $insights[] = "Visez l'excellence en travaillant les points faibles identifiés.";
        }

        if ($gradeCount < 3) {
            $insights[] = "Plus d'évaluations sont nécessaires pour une analyse complète.";
        }

        return implode(' ', $insights);
    }
}
