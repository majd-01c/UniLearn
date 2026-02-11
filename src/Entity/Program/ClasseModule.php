<?php

namespace App\Entity;

use App\Repository\ClasseModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClasseModuleRepository::class)]
#[ORM\UniqueConstraint(name: 'classe_module_unique', columns: ['classe_id', 'module_id'])]
#[ORM\Index(columns: ['classe_id'])]
#[ORM\Index(columns: ['module_id'])]
class ClasseModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Classe::class, inversedBy: 'modules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Classe $classe = null;

    #[ORM\ManyToOne(targetEntity: Module::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Module $module = null;

    /**
     * @var Collection<int, ClasseCourse>
     */
    #[ORM\OneToMany(targetEntity: ClasseCourse::class, mappedBy: 'classeModule', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function setClasse(?Classe $classe): static
    {
        $this->classe = $classe;
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
     * @return Collection<int, ClasseCourse>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(ClasseCourse $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setClasseModule($this);
        }
        return $this;
    }

    public function removeCourse(ClasseCourse $course): static
    {
        if ($this->courses->removeElement($course)) {
            if ($course->getClasseModule() === $this) {
                $course->setClasseModule(null);
            }
        }
        return $this;
    }
}
