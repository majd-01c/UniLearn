<?php

namespace App\Entity;

use App\Repository\BuildProgramCourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuildProgramCourseRepository::class)]
#[ORM\UniqueConstraint(name: 'build_prog_course_unique', columns: ['build_program_module_id', 'course_id'])]
#[ORM\Index(columns: ['build_program_module_id'])]
#[ORM\Index(columns: ['course_id'])]
class BuildProgramCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BuildProgramModule::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BuildProgramModule $buildProgramModule = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    /**
     * @var Collection<int, BuildProgramContenu>
     */
    #[ORM\OneToMany(targetEntity: BuildProgramContenu::class, mappedBy: 'buildProgramCourse', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $contenus;

    public function __construct()
    {
        $this->contenus = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuildProgramModule(): ?BuildProgramModule
    {
        return $this->buildProgramModule;
    }

    public function setBuildProgramModule(?BuildProgramModule $buildProgramModule): static
    {
        $this->buildProgramModule = $buildProgramModule;
        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    /**
     * @return Collection<int, BuildProgramContenu>
     */
    public function getContenus(): Collection
    {
        return $this->contenus;
    }

    public function addContenu(BuildProgramContenu $contenu): static
    {
        if (!$this->contenus->contains($contenu)) {
            $this->contenus->add($contenu);
            $contenu->setBuildProgramCourse($this);
        }
        return $this;
    }

    public function removeContenu(BuildProgramContenu $contenu): static
    {
        if ($this->contenus->removeElement($contenu)) {
            if ($contenu->getBuildProgramCourse() === $this) {
                $contenu->setBuildProgramCourse(null);
            }
        }
        return $this;
    }
}
