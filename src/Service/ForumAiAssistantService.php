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
            return [
                'topics' => [],
                'aiAdvice' => 'No similar topics found. You can create a new topic!',
                'fromCache' => false
            ];
        }

        // Step 2: AI enhancement (if available)
        $aiResults = [];
        if ($this->geminiApi->isAvailable() && count($keywordResults) > 0) {
            $aiResults = $this->geminiApi->findSimilarTopics($question, $keywordResults);
        }

        // Combine results
        $finalResults = $this->combineResults($keywordResults, $aiResults);
        
        // Cache the results
        $this->cacheResults($hash, $question, $finalResults, $aiResults['advice'] ?? null);
        
        return [
            'topics' => $finalResults,
            'aiAdvice' => $aiResults['advice'] ?? null,
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

        // Build keyword search
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
        // Remove common words
        $stopWords = ['how', 'what', 'when', 'where', 'why', 'who', 'the', 'is', 'are', 'was', 'were', 'can', 'could', 'should', 'would', 'do', 'does', 'did', 'i', 'my', 'me', 'you', 'your', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];
        
        // Convert to lowercase and split
        $words = preg_split('/\s+/', strtolower($text));
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        
        // Remove stop words
        $keywords = array_diff($words, $stopWords);
        
        // Return unique keywords
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
