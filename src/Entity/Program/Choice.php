<?php

namespace App\Entity;

use App\Repository\ChoiceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChoiceRepository::class)]
#[ORM\Index(columns: ['question_id'])]
class Choice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'choices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Question is required')]
    private ?Question $question = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Choice text is required')]
    private ?string $choiceText = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isCorrect = false;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Position must be zero or positive')]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getChoiceText(): ?string
    {
        return $this->choiceText;
    }

    public function setChoiceText(string $choiceText): static
    {
        $this->choiceText = $choiceText;
        return $this;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;
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
}
