<?php

namespace App\Service\JobOffer;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service to call OpenRouter API for AI-powered CV extraction.
 * OpenRouter provides access to multiple AI models (Gemini, Claude, GPT, etc.)
 */
class OpenRouterService
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    
    // Using a cost-effective model good at French text extraction
    private const DEFAULT_MODEL = 'google/gemini-flash-1.5';
    
    // Fallback free models
    private const FALLBACK_MODELS = [
        'mistralai/mistral-7b-instruct:free',
        'meta-llama/llama-3-8b-instruct:free',
    ];

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
            $this->logger->error('OpenRouter API key not configured');
            return null;
        }

        $prompt = $this->buildExtractionPrompt($cvText, $knownSkills);
        
        // Try primary model first, then fallbacks
        $models = array_merge([self::DEFAULT_MODEL], self::FALLBACK_MODELS);
        
        foreach ($models as $model) {
            try {
                $result = $this->callApi($prompt, $model);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Model failed, trying fallback', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->logger->error('All AI models failed for CV extraction');
        return null;
    }

    /**
     * Build the extraction prompt for the AI.
     */
    private function buildExtractionPrompt(string $cvText, array $knownSkills): string
    {
        $skillsList = !empty($knownSkills) 
            ? "Voici une liste de compétences à rechercher: " . implode(', ', $knownSkills) . "\n\n"
            : "";

        return <<<PROMPT
Tu es un assistant expert en analyse de CV français. Analyse le CV suivant et extrait les informations structurées.

{$skillsList}Retourne UNIQUEMENT un objet JSON valide avec cette structure exacte (sans texte avant ou après):
{
    "skills": ["compétence1", "compétence2"],
    "educationLevel": "master|licence|bac+2|bac|ingenieur|doctorat|null",
    "educationField": "domaine d'études ou null",
    "experienceYears": nombre entier ou 0,
    "languages": ["langue1", "langue2"],
    "portfolioUrls": ["url1", "url2"]
}

Règles importantes:
- Pour "skills": inclus uniquement les compétences techniques mentionnées dans le CV
- Pour "educationLevel": normalise en minuscules (master, licence, bac+2, bac, ingenieur, doctorat)
- Pour "experienceYears": calcule le nombre total d'années d'expérience professionnelle
- Pour "languages": inclus les langues parlées mentionnées
- Pour "portfolioUrls": extrait les liens GitHub, LinkedIn, portfolio, etc.

CV à analyser:
---
{$cvText}
---

JSON:
PROMPT;
    }

    /**
     * Call the OpenRouter API.
     */
    private function callApi(string $prompt, string $model): ?array
    {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://unilearn.local',
                'X-Title' => 'UniLearn ATS',
            ],
            'json' => [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1, // Low temperature for more consistent extraction
                'max_tokens' => 1000,
            ],
            'timeout' => 30,
        ]);

        $statusCode = $response->getStatusCode();
        
        if ($statusCode !== 200) {
            $this->logger->error('OpenRouter API error', [
                'model' => $model,
                'statusCode' => $statusCode,
                'response' => $response->getContent(false),
            ]);
            return null;
        }

        $data = $response->toArray();
        
        if (!isset($data['choices'][0]['message']['content'])) {
            $this->logger->error('Invalid API response structure', ['data' => $data]);
            return null;
        }

        $content = $data['choices'][0]['message']['content'];
        
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
        $content = preg_replace('/^```json?\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to parse AI response as JSON', [
                'content' => $content,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        // Validate and normalize the structure
        return [
            'skills' => $this->normalizeArray($data['skills'] ?? []),
            'educationLevel' => $this->normalizeEducationLevel($data['educationLevel'] ?? null),
            'educationField' => $data['educationField'] ?? null,
            'experienceYears' => max(0, (int) ($data['experienceYears'] ?? 0)),
            'languages' => $this->normalizeArray($data['languages'] ?? []),
            'portfolioUrls' => $this->normalizeArray($data['portfolioUrls'] ?? []),
        ];
    }

    /**
     * Normalize an array value.
     */
    private function normalizeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        
        return array_filter(array_map('trim', $value), fn($v) => !empty($v));
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
