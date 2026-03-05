<?php

namespace App\Service\JobOffer;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

    // Gemini API configuration (merged from OpenRouterService)
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const PRIMARY_MODEL = 'gemini-2.5-flash';
    private const FALLBACK_MODELS = ['gemini-2.0-flash', 'gemini-2.0-flash-lite'];

    public function __construct(
        private readonly CVParserService $cvParser,
        private readonly HttpClientInterface $httpClient,
        private readonly SkillsProvider $skillsProvider,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $cvUploadDirectory,
        private readonly string $geminiApiKey,
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
        $extractedData = $this->extractCVData($cvText, $allSkills);
        
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

    // === AI CV Extraction Methods (merged from OpenRouterService) ===

    /**
     * Extract structured data from CV text using AI.
     *
     * @param string $cvText The extracted text from the CV
     * @param array $knownSkills List of skills to look for
     * @return array|null Extracted data or null on failure
     */
    private function extractCVData(string $cvText, array $knownSkills = []): ?array
    {
        if (empty($this->geminiApiKey)) {
            $this->logger->error('Gemini API key not configured. Get a free key at https://aistudio.google.com/apikey');
            return null;
        }

        $prompt = $this->buildExtractionPrompt($cvText, $knownSkills);
        
        // Try primary model first, then fallbacks
        $models = array_merge([self::PRIMARY_MODEL], self::FALLBACK_MODELS);
        
        foreach ($models as $model) {
            // Retry up to 2 times per model (handles rate limits)
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $result = $this->callGeminiApi($prompt, $model);
                    if ($result !== null) {
                        $this->logger->info('CV data extracted successfully', ['model' => $model, 'attempt' => $attempt]);
                        return $result;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Gemini model failed', [
                        'model' => $model,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Wait before retry (rate limit cooldown)
                if ($attempt < 2) {
                    $this->logger->info('Waiting 5 seconds before retry...', ['model' => $model]);
                    sleep(5);
                }
            }
        }
        
        $this->logger->error('All Gemini models failed for CV extraction');
        return null;
    }

    /**
     * Build the extraction prompt for the AI.
     */
    private function buildExtractionPrompt(string $cvText, array $knownSkills): string
    {
        // Limit known skills list to avoid huge prompts
        $skillsHint = '';
        if (!empty($knownSkills)) {
            // Only send first 50 skills to keep prompt small
            $limitedSkills = array_slice($knownSkills, 0, 50);
            $skillsHint = "Compétences connues: " . implode(', ', $limitedSkills) . "\n";
        }

        // Truncate CV text to 8000 chars max to stay within token limits
        $cvText = mb_substr($cvText, 0, 8000);

        return <<<PROMPT
Analyse ce CV et retourne un JSON avec: skills (tableau), educationLevel (bac/bac+2/licence/master/ingenieur/doctorat ou null), educationField (string ou null), experienceYears (int), languages (tableau), portfolioUrls (tableau).
{$skillsHint}
CV:
{$cvText}
PROMPT;
    }

    /**
     * Call the Google Gemini API directly.
     */
    private function callGeminiApi(string $prompt, string $model): ?array
    {
        $url = sprintf('%s/%s:generateContent?key=%s', self::GEMINI_API_URL, $model, $this->geminiApiKey);

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 4096,
                    'responseMimeType' => 'application/json',
                ],
            ],
            'timeout' => 45,
        ]);

        $statusCode = $response->getStatusCode();
        
        if ($statusCode !== 200) {
            $errorBody = $response->getContent(false);
            $this->logger->error('Gemini API error', [
                'model' => $model,
                'statusCode' => $statusCode,
            ]);
            
            if ($statusCode === 429) {
                $this->logger->warning('Gemini API: Rate limited. Throwing exception to trigger retry.');
                throw new \RuntimeException('Rate limited (429). Will retry.');
            }
            
            return null;
        }

        $data = $response->toArray();
        
        // Gemini API response structure
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            // Check if response was truncated due to safety or length
            $finishReason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            $this->logger->error('Invalid Gemini API response', [
                'model' => $model,
                'finishReason' => $finishReason,
            ]);
            return null;
        }

        $content = $data['candidates'][0]['content']['parts'][0]['text'];
        
        $this->logger->debug('Gemini raw response', ['content' => substr($content, 0, 200)]);
        
        // Parse the JSON response
        return $this->parseJsonResponse($content);
    }

    /**
     * Parse and validate the JSON response from the AI.
     */
    private function parseJsonResponse(string $content): ?array
    {
        // Clean up the response - remove markdown code blocks if present
        $content = trim($content);
        $content = preg_replace('/^```json?\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);
        $content = trim($content);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to parse AI response as JSON', [
                'content' => substr($content, 0, 500),
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        // Validate and normalize the structure
        $result = [
            'skills' => $this->normalizeArray($data['skills'] ?? []),
            'educationLevel' => $this->normalizeEducationLevel($data['educationLevel'] ?? null),
            'educationField' => $data['educationField'] ?? null,
            'experienceYears' => max(0, (int) ($data['experienceYears'] ?? 0)),
            'languages' => $this->normalizeArray($data['languages'] ?? []),
            'portfolioUrls' => $this->normalizeArray($data['portfolioUrls'] ?? []),
        ];
        
        $this->logger->info('Parsed CV data', [
            'skillsCount' => count($result['skills']),
            'educationLevel' => $result['educationLevel'],
            'experienceYears' => $result['experienceYears'],
            'languagesCount' => count($result['languages']),
        ]);
        
        return $result;
    }

    /**
     * Normalize an array value.
     */
    private function normalizeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        
        return array_values(array_filter(array_map('trim', $value), fn($v) => !empty($v)));
    }

    /**
     * Normalize education level to standard format.
     */
    private function normalizeEducationLevel(?string $level): ?string
    {
        if (empty($level) || $level === 'null') {
            return null;
        }

        $level = strtolower(trim($level));
        
        // Map common variations
        $mappings = [
            'baccalauréat' => 'bac',
            'baccalaureat' => 'bac',
            'bts' => 'bac+2',
            'dut' => 'bac+2',
            'deug' => 'bac+2',
            'licence' => 'licence',
            'bachelor' => 'licence',
            'master' => 'master',
            'mba' => 'master',
            'ingénieur' => 'ingenieur',
            'ingenieur' => 'ingenieur',
            'doctorat' => 'doctorat',
            'phd' => 'doctorat',
        ];

        foreach ($mappings as $key => $value) {
            if (str_contains($level, $key)) {
                return $value;
            }
        }

        // Check for direct matches
        $validLevels = ['bac', 'bac+2', 'licence', 'master', 'ingenieur', 'doctorat'];
        if (in_array($level, $validLevels)) {
            return $level;
        }

        return null;
    }
}
