<?php

namespace App\Entity;

use App\Enum\ClasseStatus;
use App\Enum\Level;
use App\Enum\Specialty;
use App\Repository\ClasseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClasseRepository::class)]
#[ORM\Index(columns: ['program_id'])]
#[ORM\Index(columns: ['level'])]
#[ORM\Index(columns: ['specialty'])]
class Classe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Class name is required')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Name must be at least {{ limit }} characters')]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Program::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Program is required')]
    private ?Program $program = null;

    #[ORM\Column(type: 'string', length: 10, enumType: Level::class)]
    #[Assert\NotNull(message: 'Level is required')]
    private ?Level $level = null;

    #[ORM\Column(type: 'string', length: 50, enumType: Specialty::class)]
    #[Assert\NotNull(message: 'Specialty is required')]
    private ?Specialty $specialty = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'Capacity must be a positive number')]
    private int $capacity = 30;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'Start date is required')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'End date is required')]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ClasseStatus::class, options: ['default' => 'inactive'])]
    private ClasseStatus $status = ClasseStatus::INACTIVE;

    /**
     * @var Collection<int, ClasseModule>
     */
    #[ORM\OneToMany(targetEntity: ClasseModule::class, mappedBy: 'classe', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $modules;

    /**
     * @var Collection<int, StudentClasse>
     */
    #[ORM\OneToMany(targetEntity: StudentClasse::class, mappedBy: 'classe', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $students;

    /**
     * @var Collection<int, TeacherClasse>
     */
    #[ORM\OneToMany(targetEntity: TeacherClasse::class, mappedBy: 'classe', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $teachers;

    public function __construct()
    {
        $this->modules = new ArrayCollection();
        $this->students = new ArrayCollection();
        $this->teachers = new ArrayCollection();
        $this->status = ClasseStatus::INACTIVE;
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

    public function getProgram(): ?Program
    {
        return $this->program;
    }

    public function setProgram(?Program $program): static
    {
        $this->program = $program;
        return $this;
    }

    public function getLevel(): ?Level
    {
        return $this->level;
    }

    public function setLevel(Level $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getSpecialty(): ?Specialty
    {
        return $this->specialty;
    }

    public function setSpecialty(Specialty $specialty): static
    {
        $this->specialty = $specialty;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getStatus(): ClasseStatus
    {
        return $this->status;
    }

    public function setStatus(ClasseStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, ClasseModule>
     */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(ClasseModule $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
            $module->setClasse($this);
        }
        return $this;
    }

    public function removeModule(ClasseModule $module): static
    {
        if ($this->modules->removeElement($module)) {
            if ($module->getClasse() === $this) {
                $module->setClasse(null);
            }
        }
        return $this;
    }

    public function activate(): void
    {
        $this->status = ClasseStatus::ACTIVE;
    }

    public function deactivate(): void
    {
        $this->status = ClasseStatus::INACTIVE;
    }

    public function isActive(): bool
    {
        return $this->status === ClasseStatus::ACTIVE;
    }

    /**
     * @return Collection<int, StudentClasse>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(StudentClasse $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
            $student->setClasse($this);
        }
        return $this;
    }

    public function removeStudent(StudentClasse $student): static
    {
        if ($this->students->removeElement($student)) {
            if ($student->getClasse() === $this) {
                $student->setClasse(null);
            }
        }
        return $this;
    }

    public function getStudentCount(): int
    {
        return $this->students->filter(fn($sc) => $sc->isActive())->count();
    }

    public function isFull(): bool
    {
        return $this->getStudentCount() >= $this->capacity;
    }

    public function getRemainingCapacity(): int
    {
        return max(0, $this->capacity - $this->getStudentCount());
    }

    public function getOccupancyPercentage(): float
    {
        if ($this->capacity <= 0) {
            return 0;
        }
        return round(($this->getStudentCount() / $this->capacity) * 100, 1);
    }

    /**
     * @return Collection<int, TeacherClasse>
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(TeacherClasse $teacher): static
    {
        if (!$this->teachers->contains($teacher)) {
            $this->teachers->add($teacher);
            $teacher->setClasse($this);
        }
        return $this;
    }

    public function removeTeacher(TeacherClasse $teacher): static
    {
        if ($this->teachers->removeElement($teacher)) {
            if ($teacher->getClasse() === $this) {
                $teacher->setClasse(null);
            }
        }
        return $this;
    }

    public function getTeacherCount(): int
    {
        return $this->teachers->filter(fn($tc) => $tc->isActive())->count();
    }

    /**
     * Get the count of teachers who have created their modules
     */
    public function getTeachersWithModulesCount(): int
    {
        return $this->teachers->filter(fn($tc) => $tc->isActive() && $tc->hasCreatedModule())->count();
    }

    /**
     * Check if all assigned teachers have created their modules
     */
    public function allTeachersHaveModules(): bool
    {
        $activeTeachers = $this->teachers->filter(fn($tc) => $tc->isActive());
        if ($activeTeachers->isEmpty()) {
            return false;
        }
        return $activeTeachers->forAll(fn($key, $tc) => $tc->hasCreatedModule());
    }

    /**
     * Check if the program can be assigned to this class (all teachers have created modules)
     */
    public function canAssignProgram(): bool
    {
        return $this->getTeacherCount() > 0 && $this->allTeachersHaveModules();
    }

    /**
     * Get remaining teachers who need to create modules
     */
    public function getPendingModuleTeachersCount(): int
    {
        return $this->getTeacherCount() - $this->getTeachersWithModulesCount();
    }
}
