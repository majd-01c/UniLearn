<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiApiService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';
    
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $geminiApiKey // kept name for compatibility, now holds Groq key
    ) {}

    /**
     * Generate content using Groq API (OpenAI-compatible)
     */
    public function generateContent(string $prompt, array $context = []): ?string
    {
        if (empty($this->geminiApiKey) || $this->geminiApiKey === 'your_groq_api_key_here') {
            $this->logger->warning('Groq API key not configured');
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->geminiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1024,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            
            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            }

            $this->logger->error('Unexpected Groq API response structure', ['response' => $data]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Groq API error: ' . $e->getMessage(), [
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
        return !empty($this->geminiApiKey) && $this->geminiApiKey !== 'your_groq_api_key_here';
    }

    /**
     * Generate an AI answer for a forum topic
     */
    public function generateTopicAnswer(string $title, string $content, array $existingComments = []): ?string
    {
        $commentsContext = '';
        if (!empty($existingComments)) {
            $commentsContext = "\n\nExisting answers from other users:\n";
            foreach (array_slice($existingComments, 0, 5) as $i => $comment) {
                $isAccepted = $comment['isAccepted'] ? ' [ACCEPTED ANSWER]' : '';
                $commentsContext .= ($i + 1) . ".{$isAccepted} {$comment['content']}\n";
            }
        }

        $prompt = <<<PROMPT
You are UniLearn AI, a helpful teaching assistant for a university forum. A student posted a question:

**Title:** {$title}
**Question:** {$content}
{$commentsContext}

Provide a helpful, concise answer (max 3-4 paragraphs). Be educational and encouraging.
- If the topic has existing accepted answers, summarize and add value
- If no good answers exist, provide your best guidance
- Use simple language appropriate for university students
- If you're not sure, say so and suggest where to find more info
- Do NOT use markdown code blocks, just plain text with line breaks
PROMPT;

        return $this->generateContent($prompt);
    }

    /**
     * Check text for toxicity/bad words
     * Returns array with 'isToxic' (bool), 'reason' (string), 'severity' (low/medium/high)
     */
    public function checkToxicity(string $text): array
    {
        $prompt = <<<PROMPT
You are a content moderation assistant for a university forum. Analyze the following text for:
- Profanity or vulgar language
- Hate speech, racism, sexism
- Harassment or bullying
- Spam or promotional content
- Threats or violent language

Text to analyze: "{$text}"

Respond in JSON format ONLY:
{
  "isToxic": true/false,
  "severity": "none" | "low" | "medium" | "high",
  "reason": "brief explanation",
  "flaggedWords": ["word1", "word2"]
}
PROMPT;

        $response = $this->generateContent($prompt);
        if (!$response) {
            return ['isToxic' => false, 'severity' => 'none', 'reason' => 'Unable to check', 'flaggedWords' => []];
        }

        $result = $this->parseAiResponse($response);
        return array_merge(
            ['isToxic' => false, 'severity' => 'none', 'reason' => '', 'flaggedWords' => []],
            $result
        );
    }

    /**
     * Rate the quality of a forum answer
     * Returns array with 'score' (1-5), 'label', 'reason'
     */
    public function rateAnswerQuality(string $question, string $answer): array
    {
        $prompt = <<<PROMPT
You are evaluating the quality of an answer on a university forum.

**Question:** {$question}
**Answer:** {$answer}

Rate the answer quality from 1-5 stars based on:
- Relevance to the question
- Accuracy and correctness  
- Clarity and helpfulness
- Completeness

Respond in JSON format ONLY:
{
  "score": 1-5,
  "label": "Poor" | "Below Average" | "Average" | "Good" | "Excellent",
  "reason": "one sentence explaining the rating"
}
PROMPT;

        $response = $this->generateContent($prompt);
        if (!$response) {
            return ['score' => 0, 'label' => 'Not rated', 'reason' => 'Unable to rate'];
        }

        $result = $this->parseAiResponse($response);
        return array_merge(
            ['score' => 0, 'label' => 'Not rated', 'reason' => ''],
            $result
        );
    }
}
