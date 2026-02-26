<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiApiService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $geminiApiKey
    ) {}

    /**
     * Generate content using Gemini API
     */
    public function generateContent(string $prompt, array $context = []): ?string
    {
        if (empty($this->geminiApiKey) || $this->geminiApiKey === 'your_gemini_api_key_here') {
            $this->logger->warning('Gemini API key not configured');
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'query' => ['key' => $this->geminiApiKey],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1024,
                    ]
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }

            $this->logger->error('Unexpected Gemini API response structure', ['response' => $data]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Gemini API error: ' . $e->getMessage(), [
                'exception' => $e,
                'prompt_length' => strlen($prompt)
            ]);
            return null;
        }
    }

    /**
     * Find similar forum topics using AI
     */
    public function findSimilarTopics(string $question, array $forumTopics): array
    {
        $topicsContext = $this->formatTopicsForAi($forumTopics);
        
        $prompt = <<<PROMPT
You are a helpful teaching assistant for a university forum.

A student is asking: "$question"

Here are recent discussions from our forum:
$topicsContext

Task: Analyze the question and suggest the TOP 3 most relevant existing discussions that might help answer this question. 
For each suggestion, explain briefly (1 sentence) why it's relevant.

Respond in JSON format:
{
  "suggestions": [
    {"id": topic_id, "title": "topic title", "relevance": "why it's relevant"},
    ...
  ],
  "needsNewTopic": true/false,
  "advice": "brief advice for the student"
}
PROMPT;

        $response = $this->generateContent($prompt);
        
        if (!$response) {
            return [];
        }

        return $this->parseAiResponse($response);
    }

    /**
     * Categorize a forum topic using AI
     */
    public function suggestCategory(string $title, string $content, array $availableCategories): ?array
    {
        $categoriesText = implode(', ', array_map(fn($cat) => $cat->getName(), $availableCategories));
        
        $prompt = <<<PROMPT
You are analyzing a university forum post to suggest the best category.

Title: "$title"
Content: "$content"

Available categories: $categoriesText

Response in JSON:
{
  "suggestedCategory": "category name",
  "confidence": "high/medium/low",
  "reason": "brief explanation"
}
PROMPT;

        $response = $this->generateContent($prompt);
        
        if (!$response) {
            return null;
        }

        return $this->parseAiResponse($response);
    }

    /**
     * Format forum topics for AI context
     */
    private function formatTopicsForAi(array $topics, int $maxTopics = 10): string
    {
        $formatted = [];
        $count = 0;
        
        foreach ($topics as $topic) {
            if ($count >= $maxTopics) break;
            
            $answers = $topic->getCommentsCount();
            $status = $topic->hasAcceptedAnswers() ? '[SOLVED]' : '[OPEN]';
            
            $formatted[] = sprintf(
                "%d. %s \"%s\" - %d answers - Category: %s",
                $topic->getId(),
                $status,
                $topic->getTitle(),
                $answers,
                $topic->getCategory()?->getName() ?? 'General'
            );
            
            $count++;
        }
        
        return implode("\n", $formatted);
    }

    /**
     * Parse AI JSON response
     */
    private function parseAiResponse(string $response): array
    {
        // Extract JSON from response (AI might add text around it)
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            try {
                $data = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
                return $data ?? [];
            } catch (\JsonException $e) {
                $this->logger->error('Failed to parse AI JSON response', [
                    'response' => $response,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [];
    }

    /**
     * Check if API is configured and available
     */
    public function isAvailable(): bool
    {
        return !empty($this->geminiApiKey) && $this->geminiApiKey !== 'your_gemini_api_key_here';
    }
}
