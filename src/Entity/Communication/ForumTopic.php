<?php

namespace App\Entity;

use App\Enum\TopicStatus;
use App\Repository\ForumTopicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumTopicRepository::class)]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['is_pinned'])]
#[ORM\Index(columns: ['created_at'])]
class ForumTopic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Topic title is required')]
    #[Assert\Length(min: 5, max: 255, minMessage: 'Title must be at least 5 characters')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Topic content is required')]
    private ?string $content = null;

    #[ORM\Column(type: 'string', enumType: TopicStatus::class)]
    private TopicStatus $status = TopicStatus::OPEN;

    #[ORM\Column]
    private bool $isPinned = false;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\ManyToOne(targetEntity: ForumCategory::class, inversedBy: 'topics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ForumCategory $category = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    /**
     * @var Collection<int, ForumComment>
     */
    #[ORM\OneToMany(targetEntity: ForumComment::class, mappedBy: 'topic', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getStatus(): TopicStatus
    {
        return $this->status;
    }

    public function setStatus(TopicStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): static
    {
        $this->isPinned = $isPinned;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;
        return $this;
    }

    public function updateLastActivity(): static
    {
        $this->lastActivityAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCategory(): ?ForumCategory
    {
        return $this->category;
    }

    public function setCategory(?ForumCategory $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return Collection<int, ForumComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * Get only top-level comments (not replies to other comments)
     * @return Collection<int, ForumComment>
     */
    public function getTopLevelComments(): Collection
    {
        return $this->comments->filter(fn(ForumComment $comment) => $comment->getParent() === null);
    }

    public function addComment(ForumComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTopic($this);
            $this->updateLastActivity();
        }
        return $this;
    }

    public function removeComment(ForumComment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getTopic() === $this) {
                $comment->setTopic(null);
            }
        }
        return $this;
    }

    public function getAcceptedAnswer(): ?ForumComment
    {
        // Return first accepted answer from comments
        $accepted = $this->getAcceptedAnswers()->first();
        return $accepted ?: null;
    }

    /**
     * Get all accepted answers for this topic
     * @return Collection<int, ForumComment>
     */
    public function getAcceptedAnswers(): Collection
    {
        return $this->comments->filter(fn(ForumComment $comment) => $comment->isAccepted());
    }

    /**
     * Check if topic has any accepted answers
     */
    public function hasAcceptedAnswers(): bool
    {
        return !$this->getAcceptedAnswers()->isEmpty();
    }

    /**
     * Get count of accepted answers
     */
    public function getAcceptedAnswersCount(): int
    {
        return $this->getAcceptedAnswers()->count();
    }

    /**
     * Update topic status based on accepted answers
     */
    public function updateSolvedStatus(): void
    {
        if ($this->hasAcceptedAnswers() && $this->status === TopicStatus::OPEN) {
            $this->status = TopicStatus::SOLVED;
        } elseif (!$this->hasAcceptedAnswers() && $this->status === TopicStatus::SOLVED) {
            $this->status = TopicStatus::OPEN;
        }
    }

    public function getCommentsCount(): int
    {
        return $this->comments->count();
    }

    public function isOpen(): bool
    {
        return $this->status === TopicStatus::OPEN;
    }

    public function isSolved(): bool
    {
        return $this->status === TopicStatus::SOLVED;
    }

    public function isLocked(): bool
    {
        return $this->status === TopicStatus::LOCKED;
    }
}
