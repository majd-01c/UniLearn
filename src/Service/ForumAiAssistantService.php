<?php

namespace App\Service;

use App\Entity\ForumAiSuggestion;
use App\Enum\TopicStatus;
use App\Repository\ForumAiSuggestionRepository;
use App\Repository\ForumTopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ForumAiAssistantService
{
    public function __construct(
        private GeminiApiService $geminiApi,
        private ForumTopicRepository $topicRepository,
        private ForumAiSuggestionRepository $suggestionRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    /**
     * Get the underlying AI API service
     */
    public function getGeminiApi(): GeminiApiService
    {
        return $this->geminiApi;
    }

    /**
     * Get similar topics for a question (with caching)
     */
    public function getSimilarTopics(string $question, ?int $categoryId = null): array
    {
        // Generate hash for caching
        $hash = $this->generateQuestionHash($question, $categoryId);
        
        // Check cache first
        $cached = $this->suggestionRepository->findCachedSuggestion($hash);
        if ($cached && !$cached->isExpired()) {
            $cached->incrementUsage();
            $this->em->flush();
            
            return [
                'topics' => $cached->getSuggestions(),
                'aiAdvice' => $cached->getAiResponse(),
                'fromCache' => true
            ];
        }

        // Step 1: Fast keyword search
        $keywordResults = $this->searchByKeywords($question, $categoryId, 15);
        
        if (empty($keywordResults)) {
            // No similar topics, but still generate a direct AI answer
            $directAnswer = null;
            if ($this->geminiApi->isAvailable()) {
                $directAnswer = $this->geminiApi->generateTopicAnswer($question, $question, []);
            }
            return [
                'topics' => [],
                'aiAdvice' => $directAnswer ? null : 'No similar topics found. You can create a new topic!',
                'directAnswer' => $directAnswer,
                'fromCache' => false
            ];
        }

        // Step 2: AI enhancement (if available)
        $aiResults = [];
        $directAnswer = null;
        if ($this->geminiApi->isAvailable() && count($keywordResults) > 0) {
            $aiResults = $this->geminiApi->findSimilarTopics($question, $keywordResults);
            $directAnswer = $aiResults['directAnswer'] ?? null;
        }

        // If AI didn't return a directAnswer from findSimilarTopics, generate one
        if (!$directAnswer && $this->geminiApi->isAvailable()) {
            $directAnswer = $this->geminiApi->generateTopicAnswer($question, $question, []);
        }

        // Combine results
        $finalResults = $this->combineResults($keywordResults, $aiResults);
        
        // Cache the results
        $this->cacheResults($hash, $question, $finalResults, $aiResults['advice'] ?? null);
        
        return [
            'topics' => $finalResults,
            'aiAdvice' => $aiResults['advice'] ?? null,
            'directAnswer' => $directAnswer,
            'fromCache' => false
        ];
    }

    /**
     * Search topics by keywords (fast database search)
     */
    private function searchByKeywords(string $question, ?int $categoryId, int $limit): array
    {
        // Extract meaningful keywords
        $keywords = $this->extractKeywords($question);
        
        // Try a broad search with the full question text as well
        $results = $this->executeKeywordSearch($keywords, $categoryId, $limit);
        
        // If strict keywords returned nothing, try broader partial matching
        if (empty($results)) {
            $broadKeywords = $this->extractBroadKeywords($question);
            if (!empty($broadKeywords) && $broadKeywords !== $keywords) {
                $results = $this->executeKeywordSearch($broadKeywords, $categoryId, $limit);
            }
        }
        
        // Last resort: search with the raw question words (no stop word filtering)
        if (empty($results)) {
            $rawWords = array_unique(array_filter(
                preg_split('/\s+/', strtolower(trim($question))),
                fn($w) => strlen($w) >= 2
            ));
            if (!empty($rawWords)) {
                $results = $this->executeKeywordSearch(array_values($rawWords), $categoryId, $limit);
            }
        }
        
        return $results;
    }

    /**
     * Execute keyword-based database search
     */
    private function executeKeywordSearch(array $keywords, ?int $categoryId, int $limit): array
    {
        if (empty($keywords)) {
            return [];
        }

        $qb = $this->topicRepository->createQueryBuilder('t')
            ->leftJoin('t.comments', 'c')
            ->addSelect('c')
            ->where('t.status != :locked')
            ->setParameter('locked', TopicStatus::LOCKED)
            ->orderBy('t.viewCount', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit);

        // Build keyword search with OR conditions
        $keywordConditions = [];
        foreach ($keywords as $index => $keyword) {
            $keywordConditions[] = sprintf(
                't.title LIKE :keyword%d OR t.content LIKE :keyword%d',
                $index,
                $index
            );
            $qb->setParameter("keyword$index", "%$keyword%");
        }

        if (!empty($keywordConditions)) {
            $qb->andWhere('(' . implode(' OR ', $keywordConditions) . ')');
        }

        // Filter by category if provided
        if ($categoryId) {
            $qb->andWhere('t.category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Extract meaningful keywords from question
     */
    private function extractKeywords(string $text): array
    {
        // English + French stop words
        $stopWords = [
            // English
            'how', 'what', 'when', 'where', 'why', 'who', 'the', 'is', 'are', 'was', 'were',
            'can', 'could', 'should', 'would', 'do', 'does', 'did', 'my', 'me', 'you', 'your',
            'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'this',
            'that', 'it', 'its', 'not', 'no', 'so', 'if', 'then', 'than', 'too', 'very',
            'just', 'about', 'also', 'been', 'have', 'has', 'had', 'will', 'would',
            // French
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'est', 'son',
            'sa', 'ses', 'ce', 'cette', 'ces', 'qui', 'que', 'quoi', 'dans', 'par', 'pour',
            'sur', 'avec', 'sans', 'pas', 'plus', 'ne', 'se', 'je', 'tu', 'il', 'elle',
            'nous', 'vous', 'ils', 'elles', 'mon', 'ton', 'mes', 'tes', 'nos', 'vos',
            'comment', 'quel', 'quelle', 'quels', 'quelles', 'aux', 'au'
        ];
        
        // Convert to lowercase, remove punctuation, and split
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', strtolower($text));
        $words = preg_split('/\s+/', $cleaned);
        $words = array_filter($words, fn($w) => strlen($w) >= 2);
        
        // Remove stop words
        $keywords = array_diff($words, $stopWords);
        
        // Return unique keywords
        return array_unique(array_values($keywords));
    }

    /**
     * Extract broader keywords (less aggressive filtering, keep more words)
     */
    private function extractBroadKeywords(string $text): array
    {
        // Only remove the most basic stop words
        $basicStopWords = ['a', 'i', 'an', 'the', 'is', 'le', 'la', 'un', 'une', 'de', 'et'];
        
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', strtolower($text));
        $words = preg_split('/\s+/', $cleaned);
        $words = array_filter($words, fn($w) => strlen($w) >= 2);
        
        $keywords = array_diff($words, $basicStopWords);
        
        return array_unique(array_values($keywords));
    }

    /**
     * Combine keyword and AI results
     */
    private function combineResults(array $keywordResults, array $aiResults): array
    {
        if (empty($aiResults) || !isset($aiResults['suggestions'])) {
            // Return top 5 keyword results
            return array_slice($keywordResults, 0, 5);
        }

        // Use AI suggestions to reorder results
        $aiTopicIds = array_column($aiResults['suggestions'], 'id');
        $ordered = [];
        
        // Add AI-suggested topics first
        foreach ($aiTopicIds as $id) {
            foreach ($keywordResults as $topic) {
                if ($topic->getId() === $id && !in_array($topic, $ordered, true)) {
                    $ordered[] = $topic;
                    break;
                }
            }
        }
        
        // Fill with remaining keyword results
        foreach ($keywordResults as $topic) {
            if (!in_array($topic, $ordered, true) && count($ordered) < 5) {
                $ordered[] = $topic;
            }
        }
        
        return $ordered;
    }

    /**
     * Cache AI results
     */
    private function cacheResults(string $hash, string $question, array $topics, ?string $advice): void
    {
        try {
            $suggestion = new ForumAiSuggestion();
            $suggestion->setQuestionHash($hash);
            $suggestion->setQuestion($question);
            $suggestion->setSuggestions(array_map(fn($t) => [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'commentsCount' => $t->getCommentsCount(),
                'hasAcceptedAnswers' => $t->hasAcceptedAnswers(),
                'categoryName' => $t->getCategory()?->getName(),
                'status' => $t->getStatus()->value
            ], $topics));
            $suggestion->setAiResponse($advice);
            
            $this->em->persist($suggestion);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to cache AI suggestion', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate hash for question (for caching)
     */
    private function generateQuestionHash(string $question, ?int $categoryId): string
    {
        $normalized = strtolower(trim($question));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return hash('sha256', $normalized . '_' . ($categoryId ?? 'all'));
    }

    /**
     * Clean up old cached suggestions
     */
    public function cleanupCache(): int
    {
        return $this->suggestionRepository->cleanupExpired();
    }
}
