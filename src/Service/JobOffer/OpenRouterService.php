<?php

namespace App\Service\JobOffer;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service to call Google Gemini API for AI-powered CV extraction.
 * Uses the free tier of Gemini API (15 RPM, 1M tokens/day).
 */
class OpenRouterService
{
    // Gemini API endpoint (free tier)
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    
    // Primary model - Gemini 2.5 Flash (free, fast, great for structured extraction)
    private const PRIMARY_MODEL = 'gemini-2.5-flash';
    
    // Fallback models
    private const FALLBACK_MODELS = ['gemini-2.0-flash', 'gemini-2.0-flash-lite'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
    ) {
    }

    /**
     * Extract structured data from CV text using AI.
     *
     * @param string $cvText The extracted text from the CV
     * @param array $knownSkills List of skills to look for
     * @return array|null Extracted data or null on failure
     */
    public function extractCVData(string $cvText, array $knownSkills = []): ?array
    {
        if (empty($this->apiKey)) {
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
        $url = sprintf('%s/%s:generateContent?key=%s', self::GEMINI_API_URL, $model, $this->apiKey);

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
