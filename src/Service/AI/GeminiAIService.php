<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiAIService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {}

    /**
     * Generate content using Gemini AI
     * 
     * @param string $prompt The prompt to send to Gemini
     * @param array $options Additional options (temperature, maxOutputTokens, etc.)
     * @return string The generated text response
     * @throws \Exception If API call fails
     */
    public function generate(string $prompt, array $options = []): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured. Please set GEMINI_API_KEY in your .env file.');
        }

        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['maxOutputTokens'] ?? 8192;
        $maxRetries = 3;
        $retryDelay = 2; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', self::API_URL, [
                    'query' => ['key' => $this->apiKey],
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => $temperature,
                            'maxOutputTokens' => $maxTokens,
                        ]
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ]
                ]);

                $statusCode = $response->getStatusCode();
                
                // Handle rate limiting with retry
                if ($statusCode === 429) {
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay * $attempt); // Exponential backoff
                        continue;
                    }
                    throw new \Exception('Rate limit exceeded. Please wait a moment and try again.');
                }

                $data = $response->toArray();

                if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    throw new \Exception('Unexpected response format from Gemini API');
                }

                return $data['candidates'][0]['content']['parts'][0]['text'];

            } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                
                // Retry on rate limit
                if ($statusCode === 429 && $attempt < $maxRetries) {
                    sleep($retryDelay * $attempt);
                    continue;
                }
                
                if ($statusCode === 429) {
                    throw new \Exception('Rate limit exceeded. Please wait a minute and try again.');
                }
                
                throw new \Exception('Gemini API error: ' . $e->getMessage());
            } catch (\Exception $e) {
                throw new \Exception('Gemini API error: ' . $e->getMessage());
            }
        }

        throw new \Exception('Failed after multiple retries. Please try again later.');
    }

    /**
     * Generate quiz questions from text content
     * 
     * @param string $content The text content to generate questions from
     * @param int $numQuestions Number of questions to generate
     * @param string $difficulty Difficulty level (easy, medium, hard)
     * @return array Array of question data
     */
    public function generateQuizQuestions(string $content, int $numQuestions = 5, string $difficulty = 'medium'): array
    {
        $prompt = $this->buildQuizGenerationPrompt($content, $numQuestions, $difficulty);
        
        $response = $this->generate($prompt, [
            'temperature' => 0.7,
            'maxOutputTokens' => 4096,
        ]);

        return $this->parseQuizResponse($response);
    }

    /**
     * Evaluate a text answer against expected answer
     * 
     * @param string $questionText The question text
     * @param string $expectedAnswer The expected/correct answer
     * @param string $studentAnswer The student's answer
     * @param int $maxPoints Maximum points for this question
     * @return array ['score' => int, 'feedback' => string, 'isCorrect' => bool]
     */
    public function evaluateTextAnswer(string $questionText, string $expectedAnswer, string $studentAnswer, int $maxPoints): array
    {
        if (empty(trim($studentAnswer))) {
            return [
                'score' => 0,
                'feedback' => 'No answer provided.',
                'isCorrect' => false
            ];
        }

        $prompt = $this->buildEvaluationPrompt($questionText, $expectedAnswer, $studentAnswer, $maxPoints);
        
        $response = $this->generate($prompt, [
            'temperature' => 0.3, // Lower temperature for consistent grading
            'maxOutputTokens' => 1024,
        ]);

        return $this->parseEvaluationResponse($response, $maxPoints);
    }

    /**
     * Build prompt for quiz generation
     */
    private function buildQuizGenerationPrompt(string $content, int $numQuestions, string $difficulty): string
    {
        return <<<PROMPT
You are an expert educator creating quiz questions. Based on the following content, generate exactly {$numQuestions} multiple-choice questions.

DIFFICULTY: {$difficulty}

CONTENT:
{$content}

INSTRUCTIONS:
1. Create {$numQuestions} questions that test understanding of the key concepts
2. Each question should have 4 answer choices (A, B, C, D)
3. Mark which answer(s) are correct
4. For {$difficulty} difficulty:
   - easy: Basic recall and simple understanding
   - medium: Application and analysis
   - hard: Critical thinking and synthesis

OUTPUT FORMAT (JSON):
Return a valid JSON array with this exact structure:
```json
[
  {
    "questionText": "The question text here?",
    "type": "MCQ",
    "points": 1,
    "choices": [
      {"text": "Choice A text", "isCorrect": false},
      {"text": "Choice B text", "isCorrect": true},
      {"text": "Choice C text", "isCorrect": false},
      {"text": "Choice D text", "isCorrect": false}
    ],
    "explanation": "Brief explanation of why the correct answer is correct"
  }
]
```

IMPORTANT:
- Return ONLY valid JSON, no additional text
- Ensure exactly one or more choices are marked as correct
- Questions should be clear and unambiguous
- All choices should be plausible
PROMPT;
    }

    /**
     * Build prompt for answer evaluation
     */
    private function buildEvaluationPrompt(string $questionText, string $expectedAnswer, string $studentAnswer, int $maxPoints): string
    {
        return <<<PROMPT
You are an expert grader evaluating a student's answer. Be fair but thorough.

QUESTION: {$questionText}

EXPECTED ANSWER / KEY POINTS:
{$expectedAnswer}

STUDENT'S ANSWER:
{$studentAnswer}

MAXIMUM POINTS: {$maxPoints}

INSTRUCTIONS:
1. Compare the student's answer to the expected answer
2. Award points based on:
   - Accuracy of the information (40%)
   - Completeness of the answer (30%)
   - Understanding demonstrated (30%)
3. Provide brief, constructive feedback

OUTPUT FORMAT (JSON):
Return ONLY valid JSON with this exact structure:
```json
{
  "score": <number between 0 and {$maxPoints}>,
  "feedback": "Brief feedback explaining the score",
  "isCorrect": <true if score >= 70% of max points, false otherwise>
}
```

IMPORTANT: Return ONLY the JSON, no additional text.
PROMPT;
    }

    /**
     * Parse quiz generation response
     */
    private function parseQuizResponse(string $response): array
    {
        // Extract JSON from response (in case there's markdown code blocks)
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        }

        // Clean up the response
        $response = trim($response);
        
        // Try to find JSON array
        if (preg_match('/\[\s*\{[\s\S]*\}\s*\]/', $response, $matches)) {
            $response = $matches[0];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse quiz response: ' . json_last_error_msg());
        }

        if (!is_array($data) || empty($data)) {
            throw new \Exception('Invalid quiz response format');
        }

        // Validate and normalize structure
        $questions = [];
        foreach ($data as $q) {
            if (!isset($q['questionText']) || !isset($q['choices'])) {
                continue;
            }

            $questions[] = [
                'questionText' => $q['questionText'],
                'type' => $q['type'] ?? 'MCQ',
                'points' => $q['points'] ?? 1,
                'explanation' => $q['explanation'] ?? null,
                'choices' => array_map(function ($c) {
                    return [
                        'text' => $c['text'] ?? $c['choiceText'] ?? '',
                        'isCorrect' => $c['isCorrect'] ?? false,
                    ];
                }, $q['choices'] ?? [])
            ];
        }

        return $questions;
    }

    /**
     * Parse evaluation response
     */
    private function parseEvaluationResponse(string $response, int $maxPoints): array
    {
        // Extract JSON from response
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        }

        $response = trim($response);
        
        // Try to find JSON object
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $response = $matches[0];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Return a default response if parsing fails
            return [
                'score' => 0,
                'feedback' => 'Unable to evaluate answer automatically.',
                'isCorrect' => false
            ];
        }

        return [
            'score' => min($maxPoints, max(0, (int) ($data['score'] ?? 0))),
            'feedback' => $data['feedback'] ?? 'No feedback available.',
            'isCorrect' => $data['isCorrect'] ?? false
        ];
    }
}
