<?php

declare(strict_types=1);

namespace App\Service\JobOffer;

use App\Entity\JobApplication;
use App\Entity\JobOffer;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service to generate AI-powered motivation letters using Google Gemini.
 * Uses the student's profile info and the job offer details to craft
 * a personalized cover letter.
 */
final class MotivationLetterService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const PRIMARY_MODEL = 'gemini-2.5-flash';
    private const FALLBACK_MODELS = ['gemini-2.0-flash', 'gemini-2.0-flash-lite'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $geminiApiKey,
    ) {
    }

    /**
     * Generate a short status message for a partner to send to a candidate.
     * Based on the application score, extracted data, and whether accepted/rejected.
     *
     * @throws \RuntimeException if all AI models fail
     */
    public function generateStatusMessage(JobApplication $application, string $decision): string
    {
        if (empty($this->geminiApiKey)) {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $prompt = $this->buildStatusMessagePrompt($application, $decision);
        $models = array_merge([self::PRIMARY_MODEL], self::FALLBACK_MODELS);

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $msg = $this->callGemini($prompt, $model);
                    if ($msg !== null) {
                        $this->logger->info('Status message generated', ['model' => $model]);
                        return $msg;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Gemini failed for status message', [
                        'model' => $model, 'attempt' => $attempt, 'error' => $e->getMessage(),
                    ]);
                }
                if ($attempt < 2) { sleep(3); }
            }
        }

        throw new \RuntimeException('Unable to generate message. Please try again.');
    }

    private function buildStatusMessagePrompt(JobApplication $application, string $decision): string
    {
        $offer = $application->getOffer();
        $student = $application->getStudent();
        $score = $application->getScore();
        $breakdown = $application->getScoreBreakdown() ?? [];
        $extractedData = $application->getExtractedData() ?? [];

        $studentName = $student->getName();
        if ($student->getProfile()) {
            $fn = $student->getProfile()->getFirstName();
            $ln = $student->getProfile()->getLastName();
            if ($fn || $ln) { $studentName = trim($fn . ' ' . $ln); }
        }

        $detectedSkills = $extractedData['skills'] ?? [];
        $requiredSkills = $offer->getRequiredSkills() ?? [];
        $matchedSkills = array_intersect(array_map('strtolower', $detectedSkills), array_map('strtolower', $requiredSkills));

        $breakdownText = '';
        if (!empty($breakdown)) {
            foreach ($breakdown as $key => $info) {
                if (is_array($info)) {
                    $pts = $info['score'] ?? $info['points'] ?? '?';
                    $max = $info['max'] ?? $info['maxPoints'] ?? '?';
                    $breakdownText .= "- {$key}: {$pts}/{$max}\n";
                }
            }
        }

        $decisionLabel = $decision === 'ACCEPTED' ? 'ACCEPTED' : 'REJECTED';

        return <<<PROMPT
You are a recruitment assistant. Write a short, professional message (3-5 sentences) from an employer to a candidate explaining why their application was {$decisionLabel}.

Rules:
- Be concise, warm, and professional
- Reference specific strengths or gaps based on the data below
- If accepted: highlight what stood out
- If rejected: be respectful, mention what was missing, and encourage them
- Do NOT include subject lines, greetings like "Dear", or signatures
- Just the body message, plain text

=== CANDIDATE ===
Name: {$studentName}
Detected Skills: {$this->implodeOrNone($detectedSkills)}
Matched Required Skills: {$this->implodeOrNone(array_values($matchedSkills))}

=== JOB OFFER ===
Title: {$offer->getTitle()}
Required Skills: {$this->implodeOrNone($requiredSkills)}

=== ATS SCORE ===
Total Score: {$score}/100
{$breakdownText}

Decision: {$decisionLabel}

Write the message now:
PROMPT;
    }

    /**
     * Generate personalized AI improvement advice for a rejected/accepted application.
     * Helps students understand their score, what went wrong, and how to improve.
     *
     * @throws \RuntimeException if all AI models fail
     */
    public function generateImprovementAdvice(JobApplication $application): string
    {
        if (empty($this->geminiApiKey)) {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $prompt = $this->buildImprovementAdvicePrompt($application);
        $models = array_merge([self::PRIMARY_MODEL], self::FALLBACK_MODELS);

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $advice = $this->callGemini($prompt, $model);
                    if ($advice !== null) {
                        $this->logger->info('Improvement advice generated', ['model' => $model, 'applicationId' => $application->getId()]);
                        return $advice;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Gemini failed for improvement advice', [
                        'model' => $model, 'attempt' => $attempt, 'error' => $e->getMessage(),
                    ]);
                }
                if ($attempt < 2) { sleep(3); }
            }
        }

        throw new \RuntimeException('Unable to generate advice. Please try again.');
    }

    private function buildImprovementAdvicePrompt(JobApplication $application): string
    {
        $offer = $application->getOffer();
        $student = $application->getStudent();
        $score = $application->getScore();
        $breakdown = $application->getScoreBreakdown() ?? [];
        $extractedData = $application->getExtractedData() ?? [];
        $status = $application->getStatus()->value;
        $statusMessage = $application->getStatusMessage() ?? '';

        $studentName = $student->getName();
        if ($student->getProfile()) {
            $fn = $student->getProfile()->getFirstName();
            $ln = $student->getProfile()->getLastName();
            if ($fn || $ln) { $studentName = trim($fn . ' ' . $ln); }
        }

        $detectedSkills = $extractedData['skills'] ?? [];
        $requiredSkills = $offer->getRequiredSkills() ?? [];
        $preferredSkills = $offer->getPreferredSkills() ?? [];
        $matchedRequired = array_intersect(array_map('strtolower', $detectedSkills), array_map('strtolower', $requiredSkills));
        $missingRequired = array_diff(array_map('strtolower', $requiredSkills), array_map('strtolower', $detectedSkills));
        $matchedPreferred = array_intersect(array_map('strtolower', $detectedSkills), array_map('strtolower', $preferredSkills));
        $missingPreferred = array_diff(array_map('strtolower', $preferredSkills), array_map('strtolower', $detectedSkills));

        $breakdownText = '';
        if (!empty($breakdown)) {
            foreach ($breakdown as $key => $info) {
                if (is_array($info)) {
                    $pts = $info['score'] ?? $info['points'] ?? '?';
                    $max = $info['max'] ?? $info['maxPoints'] ?? '?';
                    $breakdownText .= "- {$key}: {$pts}/{$max}\n";
                }
            }
        }

        $education = $extractedData['education'] ?? [];
        $experience = $extractedData['experience'] ?? [];
        $educationText = !empty($education) ? implode(', ', array_map(fn($e) => is_array($e) ? ($e['degree'] ?? $e['level'] ?? json_encode($e)) : (string)$e, $education)) : 'Not detected';
        $experienceText = !empty($experience) ? implode(', ', array_map(fn($e) => is_array($e) ? ($e['title'] ?? $e['role'] ?? json_encode($e)) : (string)$e, $experience)) : 'Not detected';

        $coverLetter = $application->getMessage() ?? 'No cover letter provided';
        $minEducation = $offer->getMinEducation() ?? 'Not specified';
        $offerTitle = $offer->getTitle();
        $offerType = $offer->getType()->value;
        $offerDescription = $offer->getDescription();
        $reqLanguages = $this->implodeOrNone($offer->getRequiredLanguages() ?? []);
        $reqSkillsText = $this->implodeOrNone($requiredSkills);
        $prefSkillsText = $this->implodeOrNone($preferredSkills);
        $detectedSkillsText = $this->implodeOrNone($detectedSkills);
        $matchedRequiredText = $this->implodeOrNone(array_values($matchedRequired));
        $missingRequiredText = $this->implodeOrNone(array_values($missingRequired));
        $matchedPreferredText = $this->implodeOrNone(array_values($matchedPreferred));
        $missingPreferredText = $this->implodeOrNone(array_values($missingPreferred));

        return <<<PROMPT
You are a friendly and expert career coach helping a student understand their job application results and improve for next time.

The student applied to a job and received a decision. Based on all the data below, provide personalized, actionable advice.

Your response MUST follow this exact structure using these headers (use markdown formatting):

## 📊 Your Score Analysis
Explain their ATS score and what each category means. Be specific about where they scored well and where they lost points.

## ❌ Why You Were {$status}
Explain clearly and kindly why they received this decision. Reference specific missing skills, experience gaps, or other factors.

## ✅ What You Did Well
Highlight their strengths — matched skills, good cover letter elements, relevant experience. Be encouraging.

## 🚀 How to Improve for Next Time
Give 4-6 specific, actionable tips. For each tip:
- Be specific (e.g., "Learn Python through freeCodeCamp" not just "learn more skills")
- Suggest free resources, courses, or certifications when relevant
- Mention what would have the biggest impact on their score

## 💡 Quick Action Plan
A short numbered list (3-4 items) of the most impactful things they can do RIGHT NOW to improve their chances.

Rules:
- Be warm, encouraging, and constructive — never harsh or discouraging
- Use the actual data (skills, scores, requirements) — don't be generic
- If they were ACCEPTED, focus on what they did right and how to keep improving
- Keep total response under 600 words
- Use plain language a student would understand

=== STUDENT ===
Name: {$studentName}
Skills Detected from CV: {$detectedSkillsText}
Education: {$educationText}
Experience: {$experienceText}
Cover Letter: {$coverLetter}

=== JOB OFFER ===
Title: {$offerTitle}
Type: {$offerType}
Required Skills: {$reqSkillsText}
Preferred Skills: {$prefSkillsText}
Min Education: {$minEducation}
Required Languages: {$reqLanguages}
Description: {$offerDescription}

=== MATCHING ANALYSIS ===
Matched Required Skills: {$matchedRequiredText}
Missing Required Skills: {$missingRequiredText}
Matched Preferred Skills: {$matchedPreferredText}
Missing Preferred Skills: {$missingPreferredText}

=== ATS SCORE ===
Total Score: {$score}/100
{$breakdownText}

=== DECISION ===
Status: {$status}
Employer Message: {$statusMessage}

Generate the personalized advice now:
PROMPT;
    }

    private function implodeOrNone(array $items): string
    {
        return !empty($items) ? implode(', ', $items) : 'None';
    }

    /**
     * Generate a motivation letter for a student applying to a job offer.
     *
     * @throws \RuntimeException if all AI models fail
     */
    public function generate(User $student, JobOffer $offer): string
    {
        if (empty($this->geminiApiKey)) {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $prompt = $this->buildPrompt($student, $offer);

        // Try primary model first, then fallbacks
        $models = array_merge([self::PRIMARY_MODEL], self::FALLBACK_MODELS);

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $letter = $this->callGemini($prompt, $model);
                    if ($letter !== null) {
                        $this->logger->info('Motivation letter generated', ['model' => $model]);
                        return $letter;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Gemini model failed for motivation letter', [
                        'model' => $model,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($attempt < 2) {
                    sleep(3);
                }
            }
        }

        throw new \RuntimeException('Unable to generate motivation letter. Please try again later.');
    }

    private function buildPrompt(User $student, JobOffer $offer): string
    {
        // Gather student info
        $profile = $student->getProfile();
        $studentName = $student->getName();
        if ($profile) {
            $firstName = $profile->getFirstName();
            $lastName = $profile->getLastName();
            if ($firstName || $lastName) {
                $studentName = trim($firstName . ' ' . $lastName);
            }
        }

        $skills = $student->getSkills() ?? [];
        $about = $student->getAbout() ?? '';
        $location = $student->getLocation() ?? '';

        // Gather offer info
        $offerTitle = $offer->getTitle();
        $offerType = $offer->getType()->value;
        $offerLocation = $offer->getLocation() ?? '';
        $offerDescription = mb_substr($offer->getDescription() ?? '', 0, 3000);
        $offerRequirements = mb_substr($offer->getRequirements() ?? '', 0, 2000);
        $requiredSkills = $offer->getRequiredSkills() ?? [];
        $preferredSkills = $offer->getPreferredSkills() ?? [];
        $minEducation = $offer->getMinEducation() ?? '';
        $requiredLanguages = $offer->getRequiredLanguages() ?? [];

        $skillsList = !empty($skills) ? implode(', ', $skills) : 'Not specified';
        $reqSkillsList = !empty($requiredSkills) ? implode(', ', $requiredSkills) : 'Not specified';
        $prefSkillsList = !empty($preferredSkills) ? implode(', ', $preferredSkills) : 'Not specified';
        $langList = !empty($requiredLanguages) ? implode(', ', $requiredLanguages) : 'Not specified';

        return <<<PROMPT
You are a career advisor helping a student write a professional and compelling motivation letter (cover letter) for a job application.

Write a motivation letter based on the following information. The letter should:
- Be professional, warm, and enthusiastic
- Highlight how the student's skills and background match the job requirements
- Be 250-400 words long
- Use a clean, formal letter format (no placeholders like [Company Name] — use available info)
- Be written in English
- NOT include any subject line or addresses — just the body of the letter
- Start with "Dear Hiring Manager," and end with a professional closing

=== STUDENT PROFILE ===
Name: {$studentName}
Location: {$location}
Skills: {$skillsList}
About: {$about}

=== JOB OFFER ===
Title: {$offerTitle}
Type: {$offerType}
Location: {$offerLocation}
Description:
{$offerDescription}

Requirements:
{$offerRequirements}

Required Skills: {$reqSkillsList}
Preferred Skills: {$prefSkillsList}
Minimum Education: {$minEducation}
Required Languages: {$langList}

Write the motivation letter now:
PROMPT;
    }

    private function callGemini(string $prompt, string $model): ?string
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
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2048,
                ],
            ],
            'timeout' => 30,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $this->logger->error('Gemini API error for motivation letter', [
                'model' => $model,
                'statusCode' => $statusCode,
            ]);

            if ($statusCode === 429) {
                throw new \RuntimeException('Rate limited (429). Will retry.');
            }

            return null;
        }

        $data = $response->toArray();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $this->logger->error('Invalid Gemini response for motivation letter', ['model' => $model]);
            return null;
        }

        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }
}
