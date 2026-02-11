<?php

namespace App\Entity;

use App\Repository\ChoiceRepository;
use Doctrine\ORM\Mapping as ORM;

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
    private ?Question $question = null;

    #[ORM\Column(type: 'text')]
    private ?string $choiceText = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isCorrect = false;

    #[ORM\Column]
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
