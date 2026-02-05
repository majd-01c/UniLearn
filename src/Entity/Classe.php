<?php

namespace App\Entity;

use App\Enum\ClasseStatus;
use App\Repository\ClasseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClasseRepository::class)]
#[ORM\Index(columns: ['program_id'])]
class Classe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Program::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Program $program = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ClasseStatus::class, options: ['default' => 'inactive'])]
    private ClasseStatus $status = ClasseStatus::INACTIVE;

    /**
     * @var Collection<int, ClasseModule>
     */
    #[ORM\OneToMany(targetEntity: ClasseModule::class, mappedBy: 'classe', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $modules;

    public function __construct()
    {
        $this->modules = new ArrayCollection();
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
}
