<?php

namespace App\Entity;

use App\Repository\AnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ORM\Index(columns: ['user_answer_id'])]
#[ORM\Index(columns: ['question_id'])]
class Answer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserAnswer::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserAnswer $userAnswer = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

    #[ORM\ManyToOne(targetEntity: Choice::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Choice $selectedChoice = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textAnswer = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isCorrect = false;

    #[ORM\Column(nullable: true)]
    private ?int $pointsEarned = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserAnswer(): ?UserAnswer
    {
        return $this->userAnswer;
    }

    public function setUserAnswer(?UserAnswer $userAnswer): static
    {
        $this->userAnswer = $userAnswer;
        return $this;
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

    public function getSelectedChoice(): ?Choice
    {
        return $this->selectedChoice;
    }

    public function setSelectedChoice(?Choice $selectedChoice): static
    {
        $this->selectedChoice = $selectedChoice;
        return $this;
    }

    public function getTextAnswer(): ?string
    {
        return $this->textAnswer;
    }

    public function setTextAnswer(?string $textAnswer): static
    {
        $this->textAnswer = $textAnswer;
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

    public function getPointsEarned(): ?int
    {
        return $this->pointsEarned;
    }

    public function setPointsEarned(?int $pointsEarned): static
    {
        $this->pointsEarned = $pointsEarned;
        return $this;
    }

    public function checkCorrectness(): void
    {
        if ($this->selectedChoice !== null) {
            $this->isCorrect = $this->selectedChoice->isCorrect();
            $this->pointsEarned = $this->isCorrect ? $this->question->getPoints() : 0;
        }
    }
}
