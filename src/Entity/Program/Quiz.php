<?php

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Index(columns: ['contenu_id'])]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Contenu::class, inversedBy: 'quiz')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    #[Assert\NotNull(message: 'Content is required')]
    private ?Contenu $contenu = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Quiz title is required')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Title must be at least {{ limit }} characters')]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Description cannot exceed {{ limit }} characters')]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Passing score must be zero or positive')]
    private ?int $passingScore = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'Time limit must be a positive number')]
    private ?int $timeLimit = null; // in minutes

    #[ORM\Column(options: ['default' => true])]
    private bool $shuffleQuestions = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $shuffleChoices = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $showCorrectAnswers = false;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'quiz', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $questions;

    /**
     * @var Collection<int, UserAnswer>
     */
    #[ORM\OneToMany(targetEntity: UserAnswer::class, mappedBy: 'quiz', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $userAnswers;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->userAnswers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?Contenu
    {
        return $this->contenu;
    }

    public function setContenu(?Contenu $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPassingScore(): ?int
    {
        return $this->passingScore;
    }

    public function setPassingScore(?int $passingScore): static
    {
        $this->passingScore = $passingScore;
        return $this;
    }

    public function getTimeLimit(): ?int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(?int $timeLimit): static
    {
        $this->timeLimit = $timeLimit;
        return $this;
    }

    public function isShuffleQuestions(): bool
    {
        return $this->shuffleQuestions;
    }

    public function setShuffleQuestions(bool $shuffleQuestions): static
    {
        $this->shuffleQuestions = $shuffleQuestions;
        return $this;
    }

    public function isShuffleChoices(): bool
    {
        return $this->shuffleChoices;
    }

    public function setShuffleChoices(bool $shuffleChoices): static
    {
        $this->shuffleChoices = $shuffleChoices;
        return $this;
    }

    public function isShowCorrectAnswers(): bool
    {
        return $this->showCorrectAnswers;
    }

    public function setShowCorrectAnswers(bool $showCorrectAnswers): static
    {
        $this->showCorrectAnswers = $showCorrectAnswers;
        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuiz($this);
        }
        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getQuiz() === $this) {
                $question->setQuiz(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, UserAnswer>
     */
    public function getUserAnswers(): Collection
    {
        return $this->userAnswers;
    }

    public function addUserAnswer(UserAnswer $userAnswer): static
    {
        if (!$this->userAnswers->contains($userAnswer)) {
            $this->userAnswers->add($userAnswer);
            $userAnswer->setQuiz($this);
        }
        return $this;
    }

    public function removeUserAnswer(UserAnswer $userAnswer): static
    {
        if ($this->userAnswers->removeElement($userAnswer)) {
            if ($userAnswer->getQuiz() === $this) {
                $userAnswer->setQuiz(null);
            }
        }
        return $this;
    }

    public function getTotalPoints(): int
    {
        $total = 0;
        foreach ($this->questions as $question) {
            $total += $question->getPoints();
        }
        return $total;
    }
}
