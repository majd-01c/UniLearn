<?php

namespace App\Entity;

use App\Repository\ForumCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ForumCategoryRepository::class)]
#[ORM\Index(columns: ['position'])]
class ForumCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Category name is required')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, ForumTopic>
     */
    #[ORM\OneToMany(targetEntity: ForumTopic::class, mappedBy: 'category', orphanRemoval: true)]
    #[ORM\OrderBy(['isPinned' => 'DESC', 'createdAt' => 'DESC'])]
    private Collection $topics;

    public function __construct()
    {
        $this->topics = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    /**
     * @return Collection<int, ForumTopic>
     */
    public function getTopics(): Collection
    {
        return $this->topics;
    }

    public function addTopic(ForumTopic $topic): static
    {
        if (!$this->topics->contains($topic)) {
            $this->topics->add($topic);
            $topic->setCategory($this);
        }
        return $this;
    }

    public function removeTopic(ForumTopic $topic): static
    {
        if ($this->topics->removeElement($topic)) {
            if ($topic->getCategory() === $this) {
                $topic->setCategory(null);
            }
        }
        return $this;
    }

    public function getTopicsCount(): int
    {
        return $this->topics->count();
    }

    public function getRepliesCount(): int
    {
        $count = 0;
        foreach ($this->topics as $topic) {
            $count += $topic->getReplies()->count();
        }
        return $count;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
