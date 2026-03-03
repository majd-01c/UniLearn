<?php

namespace App\Entity;

use App\Repository\ForumAiSuggestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumAiSuggestionRepository::class)]
#[ORM\Table(name: 'forum_ai_suggestion')]
#[ORM\Index(columns: ['question_hash'])]
#[ORM\Index(columns: ['created_at'])]
class ForumAiSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $questionHash = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $question = null;

    #[ORM\Column(type: Types::JSON)]
    private array $suggestions = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiResponse = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private int $usageCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        // Cache expires after 30 days
        $this->expiresAt = new \DateTimeImmutable('+30 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestionHash(): ?string
    {
        return $this->questionHash;
    }

    public function setQuestionHash(string $questionHash): static
    {
        $this->questionHash = $questionHash;
        return $this;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    public function setSuggestions(array $suggestions): static
    {
        $this->suggestions = $suggestions;
        return $this;
    }

    public function getAiResponse(): ?string
    {
        return $this->aiResponse;
    }

    public function setAiResponse(?string $aiResponse): static
    {
        $this->aiResponse = $aiResponse;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function setUsageCount(int $usageCount): static
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    public function incrementUsage(): static
    {
        $this->usageCount++;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}
