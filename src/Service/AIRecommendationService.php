<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\GradeRepository;
use Doctrine\ORM\EntityManagerInterface;

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
        private GradeRepository $gradeRepository
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
            ->join('a.course', 'c')
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
            $courseName = $grade->getAssessment()->getCourse()->getTitle();
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
                $recommendations[] = [
                    'courseName' => $courseName,
                    'average' => round($average, 2),
                    'status' => $this->getStatusLabel($average),
                    'priority' => $this->calculatePriority($average),
                    'recommendedCourses' => $this->getRecommendedCourses($courseName),
                    'aiInsight' => $this->generateAIInsight($average, count($grades)),
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
            ->join('a.course', 'c')
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
            $courseName = $grade->getAssessment()->getCourse()->getTitle();
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
