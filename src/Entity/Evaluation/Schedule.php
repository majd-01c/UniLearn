<?php

namespace App\Entity;

use App\Repository\ScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScheduleRepository::class)]
class Schedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Classe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Class is required')]
    private ?Classe $classe = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Course is required')]
    private ?Course $course = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Day of week is required')]
    #[Assert\Choice(choices: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], message: 'Invalid day of week')]
    private ?string $dayOfWeek = null; // monday, tuesday, etc.

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'Start time is required')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'End time is required')]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $room = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $teacher = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function setClasse(?Classe $classe): self
    {
        $this->classe = $classe;
        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): self
    {
        $this->course = $course;
        return $this;
    }

    public function getDayOfWeek(): ?string
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(string $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getRoom(): ?string
    {
        return $this->room;
    }

    public function setRoom(?string $room): self
    {
        $this->room = $room;
        return $this;
    }

    public function getTeacher(): ?User
    {
        return $this->teacher;
    }

    public function setTeacher(?User $teacher): self
    {
        $this->teacher = $teacher;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }
}
