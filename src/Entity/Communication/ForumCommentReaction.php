<?php

namespace App\Entity;

use App\Repository\ForumCommentReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumCommentReactionRepository::class)]
#[ORM\Table(name: 'forum_comment_reaction')]
#[ORM\UniqueConstraint(name: 'user_comment_reaction_unique', columns: ['user_id', 'comment_id'])]
#[ORM\Index(columns: ['comment_id'])]
class ForumCommentReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ForumComment::class, inversedBy: 'reactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ForumComment $comment = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null; // 'like' or 'dislike'

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getComment(): ?ForumComment
    {
        return $this->comment;
    }

    public function setComment(?ForumComment $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function isLike(): bool
    {
        return $this->type === 'like';
    }

    public function isDislike(): bool
    {
        return $this->type === 'dislike';
    }
}
