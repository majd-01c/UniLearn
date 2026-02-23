<?php

namespace App\Service\JobOffer;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to calculate ATS scores for job applications.
 * 
 * Scoring breakdown (100 points total):
 * - Required Skills: 40 points
 * - Preferred Skills: 15 points
 * - Education Level: 20 points
 * - Experience Years: 15 points
 * - Languages: 10 points
 */
class ATSScoringService
{
    private const MAX_SCORE = 100;
    
    private const WEIGHT_REQUIRED_SKILLS = 40;
    private const WEIGHT_PREFERRED_SKILLS = 15;
    private const WEIGHT_EDUCATION = 20;
    private const WEIGHT_EXPERIENCE = 15;
    private const WEIGHT_LANGUAGES = 10;

    public function __construct(
        private readonly CVParserService $cvParser,
        private readonly OpenRouterService $openRouter,
        private readonly SkillsProvider $skillsProvider,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $cvUploadDirectory,
    ) {
    }

    /**
     * Calculate score for a single application.
     */
    public function calculateScore(JobApplication $application): array
    {
        $offer = $application->getOffer();
        
        // Get or extract CV data
        $extractedData = $this->getExtractedData($application);
        
        if ($extractedData === null) {
            return [
                'score' => 0,
                'breakdown' => [
                    'error' => 'Could not extract data from CV',
                ],
                'extractedData' => null,
            ];
        }

        // Calculate individual scores
        $breakdown = [];
        
        // 1. Required Skills Score (40 points)
        $requiredSkillsScore = $this->calculateSkillsScore(
            $extractedData['skills'] ?? [],
            $offer->getRequiredSkills(),
            self::WEIGHT_REQUIRED_SKILLS
        );
        $breakdown['requiredSkills'] = $requiredSkillsScore;
        
        // 2. Preferred Skills Score (15 points)
        $preferredSkillsScore = $this->calculateSkillsScore(
            $extractedData['skills'] ?? [],
            $offer->getPreferredSkills(),
            self::WEIGHT_PREFERRED_SKILLS
        );
        $breakdown['preferredSkills'] = $preferredSkillsScore;
        
        // 3. Education Score (20 points)
        $educationScore = $this->calculateEducationScore(
            $extractedData['educationLevel'] ?? null,
            $offer->getMinEducation()
        );
        $breakdown['education'] = $educationScore;
        
        // 4. Experience Score (15 points)
        $experienceScore = $this->calculateExperienceScore(
            $extractedData['experienceYears'] ?? 0,
            $offer->getMinExperienceYears() ?? 0
        );
        $breakdown['experience'] = $experienceScore;
        
        // 5. Languages Score (10 points)
        $languagesScore = $this->calculateLanguagesScore(
            $extractedData['languages'] ?? [],
            $offer->getRequiredLanguages()
        );
        $breakdown['languages'] = $languagesScore;
        
        // Total score
        $totalScore = (int) round(
            $requiredSkillsScore['score'] +
            $preferredSkillsScore['score'] +
            $educationScore['score'] +
            $experienceScore['score'] +
            $languagesScore['score']
        );
        
        // Cap at max score
        $totalScore = min($totalScore, self::MAX_SCORE);
        
        // Save to application
        $application->setScore($totalScore);
        $application->setScoreBreakdown($breakdown);
        $application->setScoredAt(new \DateTimeImmutable());
        $application->setExtractedData($extractedData);
        
        $this->em->flush();
        
        $this->logger->info('Calculated ATS score', [
            'applicationId' => $application->getId(),
            'score' => $totalScore,
        ]);
        
        return [
            'score' => $totalScore,
            'breakdown' => $breakdown,
            'extractedData' => $extractedData,
        ];
    }

    /**
     * Calculate scores for all applications of an offer.
     */
    public function calculateScoresForOffer(JobOffer $offer): array
    {
        $results = [];
        
        foreach ($offer->getApplications() as $application) {
            $results[$application->getId()] = $this->calculateScore($application);
        }
        
        return $results;
    }

    /**
     * Get extracted data from CV (cached or new extraction).
     */
    private function getExtractedData(JobApplication $application): ?array
    {
        // Return cached data if available and recent
        $existingData = $application->getExtractedData();
        if ($existingData !== null && !empty($existingData)) {
            return $existingData;
        }
        
        // Extract from CV
        $cvFileName = $application->getCvFileName();
        if (empty($cvFileName)) {
            $this->logger->warning('No CV file for application', [
                'applicationId' => $application->getId(),
            ]);
            return null;
        }
        
        $cvPath = $this->cvUploadDirectory . '/' . $cvFileName;
        
        // Extract text from PDF
        $cvText = $this->cvParser->extractTextFromPdf($cvPath);
        if (empty($cvText)) {
            $this->logger->warning('Could not extract text from CV', [
                'applicationId' => $application->getId(),
                'cvPath' => $cvPath,
            ]);
            return null;
        }
        
        // Use AI to extract structured data
        $allSkills = $this->skillsProvider->getAllSkills();
        $extractedData = $this->openRouter->extractCVData($cvText, $allSkills);
        
        if ($extractedData === null) {
            $this->logger->warning('AI extraction failed', [
                'applicationId' => $application->getId(),
            ]);
            return null;
        }
        
        return $extractedData;
    }

    /**
     * Calculate skills matching score.
     */
    private function calculateSkillsScore(array $candidateSkills, array $requiredSkills, float $maxPoints): array
    {
        if (empty($requiredSkills)) {
            // If no requirements, give full points
            return [
                'score' => $maxPoints,
                'matched' => [],
                'missing' => [],
                'total' => 0,
            ];
        }
        
        // Normalize skills for comparison
        $candidateSkillsNormalized = array_map(
            fn($s) => strtolower(trim($s)),
            $candidateSkills
        );
        
        $matched = [];
        $missing = [];
        
        foreach ($requiredSkills as $skill) {
            $skillNormalized = strtolower(trim($skill));
            
            // Check for exact or partial match
            $found = false;
            foreach ($candidateSkillsNormalized as $candidateSkill) {
                if ($candidateSkill === $skillNormalized || 
                    str_contains($candidateSkill, $skillNormalized) ||
                    str_contains($skillNormalized, $candidateSkill)) {
                    $matched[] = $skill;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $missing[] = $skill;
            }
        }
        
        $matchPercentage = count($matched) / count($requiredSkills);
        $score = $matchPercentage * $maxPoints;
        
        return [
            'score' => round($score, 1),
            'matched' => $matched,
            'missing' => $missing,
            'total' => count($requiredSkills),
        ];
    }

    /**
     * Calculate education score.
     */
    private function calculateEducationScore(?string $candidateLevel, ?string $requiredLevel): array
    {
        if (empty($requiredLevel)) {
            // No requirement = full points
            return [
                'score' => self::WEIGHT_EDUCATION,
                'candidateLevel' => $candidateLevel,
                'requiredLevel' => null,
                'meetsRequirement' => true,
            ];
        }
        
        if (empty($candidateLevel)) {
            return [
                'score' => 0,
                'candidateLevel' => null,
                'requiredLevel' => $requiredLevel,
                'meetsRequirement' => false,
            ];
        }
        
        $candidateWeight = $this->skillsProvider->getEducationWeight($candidateLevel);
        $requiredWeight = $this->skillsProvider->getEducationWeight($requiredLevel);
        
        if ($candidateWeight >= $requiredWeight) {
            // Meets or exceeds requirement
            return [
                'score' => self::WEIGHT_EDUCATION,
                'candidateLevel' => $candidateLevel,
                'requiredLevel' => $requiredLevel,
                'meetsRequirement' => true,
            ];
        }
        
        // Partial score based on how close they are
        $ratio = $requiredWeight > 0 ? $candidateWeight / $requiredWeight : 0;
        $score = $ratio * self::WEIGHT_EDUCATION;
        
        return [
            'score' => round($score, 1),
            'candidateLevel' => $candidateLevel,
            'requiredLevel' => $requiredLevel,
            'meetsRequirement' => false,
        ];
    }

    /**
     * Calculate experience score.
     */
    private function calculateExperienceScore(int $candidateYears, int $requiredYears): array
    {
        if ($requiredYears <= 0) {
            // No requirement = full points
            return [
                'score' => self::WEIGHT_EXPERIENCE,
                'candidateYears' => $candidateYears,
                'requiredYears' => 0,
                'meetsRequirement' => true,
            ];
        }
        
        if ($candidateYears >= $requiredYears) {
            // Meets requirement
            return [
                'score' => self::WEIGHT_EXPERIENCE,
                'candidateYears' => $candidateYears,
                'requiredYears' => $requiredYears,
                'meetsRequirement' => true,
            ];
        }
        
        // Partial score
        $ratio = $candidateYears / $requiredYears;
        $score = $ratio * self::WEIGHT_EXPERIENCE;
        
        return [
            'score' => round($score, 1),
            'candidateYears' => $candidateYears,
            'requiredYears' => $requiredYears,
            'meetsRequirement' => false,
        ];
    }

    /**
     * Calculate languages score.
     */
    private function calculateLanguagesScore(array $candidateLanguages, array $requiredLanguages): array
    {
        if (empty($requiredLanguages)) {
            // No requirement = full points
            return [
                'score' => self::WEIGHT_LANGUAGES,
                'matched' => [],
                'missing' => [],
            ];
        }
        
        // Normalize languages for comparison
        $candidateLanguagesNormalized = array_map(
            fn($l) => strtolower(trim($l)),
            $candidateLanguages
        );
        
        $matched = [];
        $missing = [];
        
        foreach ($requiredLanguages as $lang) {
            $langNormalized = strtolower(trim($lang));
            
            $found = false;
            foreach ($candidateLanguagesNormalized as $candidateLang) {
                if ($candidateLang === $langNormalized || 
                    str_contains($candidateLang, $langNormalized) ||
                    str_contains($langNormalized, $candidateLang)) {
                    $matched[] = $lang;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $missing[] = $lang;
            }
        }
        
        $matchPercentage = count($matched) / count($requiredLanguages);
        $score = $matchPercentage * self::WEIGHT_LANGUAGES;
        
        return [
            'score' => round($score, 1),
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    /**
     * Get score color class based on score value.
     */
    public static function getScoreColorClass(int $score): string
    {
        if ($score >= 75) {
            return 'success'; // Green
        }
        if ($score >= 50) {
            return 'warning'; // Yellow
        }
        return 'danger'; // Red
    }
}
