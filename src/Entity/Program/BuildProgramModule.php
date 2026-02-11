<?php

namespace App\Entity;

use App\Repository\BuildProgramModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuildProgramModuleRepository::class)]
#[ORM\UniqueConstraint(name: 'build_prog_module_unique', columns: ['build_program_id', 'module_id'])]
#[ORM\Index(columns: ['build_program_id'])]
#[ORM\Index(columns: ['module_id'])]
class BuildProgramModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BuildProgram::class, inversedBy: 'modules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BuildProgram $buildProgram = null;

    #[ORM\ManyToOne(targetEntity: Module::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Module $module = null;

    /**
     * @var Collection<int, BuildProgramCourse>
     */
    #[ORM\OneToMany(targetEntity: BuildProgramCourse::class, mappedBy: 'buildProgramModule', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuildProgram(): ?BuildProgram
    {
        return $this->buildProgram;
    }

    public function setBuildProgram(?BuildProgram $buildProgram): static
    {
        $this->buildProgram = $buildProgram;
        return $this;
    }

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(?Module $module): static
    {
        $this->module = $module;
        return $this;
    }

    /**
     * @return Collection<int, BuildProgramCourse>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(BuildProgramCourse $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setBuildProgramModule($this);
        }
        return $this;
    }

    public function removeCourse(BuildProgramCourse $course): static
    {
        if ($this->courses->removeElement($course)) {
            if ($course->getBuildProgramModule() === $this) {
                $course->setBuildProgramModule(null);
            }
        }
        return $this;
    }
}
