<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GroqApiService
{
    private const API_URL  = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL    = 'llama-3.3-70b-versatile';
    private const TIMEOUT  = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private string              $groqApiKey
    ) {}

    public function isAvailable(): bool
    {
        return !empty($this->groqApiKey) && $this->groqApiKey !== 'your_groq_api_key_here';
    }

    /**
     * Send a chat prompt and return the assistant message text.
     */
    public function chat(string $systemPrompt, string $userMessage, float $temperature = 0.6): ?string
    {
        if (!$this->isAvailable()) {
            $this->logger->warning('Groq API key not configured.');
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'temperature' => $temperature,
                    'max_tokens'  => 2048,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userMessage],
                    ],
                ],
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray();

            return $data['choices'][0]['message']['content'] ?? null;

        } catch (\Exception $e) {
            $this->logger->error('Groq API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse JSON from a potentially markdown-fenced AI response.
     */
    public function parseJson(string $raw): ?array
    {
        // Strip ```json ... ``` fences if present
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = preg_replace('/```\s*$/m', '', $clean);

        // Extract first {...} or [...]
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/u', $clean, $matches)) {
            try {
                return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning('Groq JSON parse failed: ' . $e->getMessage());
            }
        }

        return null;
    }
}
