<?php

namespace App\Entity;

use App\Repository\ModuleRepository;
use App\Enum\PeriodUnit;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleRepository::class)]
class Module
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', enumType: PeriodUnit::class)]
    private ?PeriodUnit $periodUnit = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, ProgramModule>
     */
    #[ORM\OneToMany(targetEntity: ProgramModule::class, mappedBy: 'module', orphanRemoval: true)]
    private Collection $programs;

    /**
     * @var Collection<int, ModuleCourse>
     */
    #[ORM\OneToMany(targetEntity: ModuleCourse::class, mappedBy: 'module', orphanRemoval: true)]
    private Collection $courses;

    public function __construct()
    {
        $this->programs = new ArrayCollection();
        $this->courses = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getPeriodUnit(): ?PeriodUnit
    {
        return $this->periodUnit;
    }

    public function setPeriodUnit(PeriodUnit $periodUnit): static
    {
        $this->periodUnit = $periodUnit;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, ProgramModule>
     */
    public function getPrograms(): Collection
    {
        return $this->programs;
    }

    public function addProgram(ProgramModule $program): static
    {
        if (!$this->programs->contains($program)) {
            $this->programs->add($program);
            $program->setModule($this);
        }
        return $this;
    }

    public function removeProgram(ProgramModule $program): static
    {
        if ($this->programs->removeElement($program)) {
            if ($program->getModule() === $this) {
                $program->setModule(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ModuleCourse>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(ModuleCourse $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setModule($this);
        }
        return $this;
    }

    public function removeCourse(ModuleCourse $course): static
    {
        if ($this->courses->removeElement($course)) {
            if ($course->getModule() === $this) {
                $course->setModule(null);
            }
        }
        return $this;
    }
}
