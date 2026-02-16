<?php

namespace App\Entity;

use App\Repository\ClasseCourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClasseCourseRepository::class)]
#[ORM\UniqueConstraint(name: 'classe_course_unique', columns: ['classe_module_id', 'course_id'])]
#[ORM\Index(columns: ['classe_module_id'])]
#[ORM\Index(columns: ['course_id'])]
class ClasseCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClasseModule::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClasseModule $classeModule = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    /**
     * @var Collection<int, ClasseContenu>
     */
    #[ORM\OneToMany(targetEntity: ClasseContenu::class, mappedBy: 'classeCourse', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $contenus;

    #[ORM\Column]
    private bool $isHidden = false;

    public function __construct()
    {
        $this->contenus = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClasseModule(): ?ClasseModule
    {
        return $this->classeModule;
    }

    public function setClasseModule(?ClasseModule $classeModule): static
    {
        $this->classeModule = $classeModule;
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
     * @return Collection<int, ClasseContenu>
     */
    public function getContenus(): Collection
    {
        return $this->contenus;
    }

    public function addContenu(ClasseContenu $contenu): static
    {
        if (!$this->contenus->contains($contenu)) {
            $this->contenus->add($contenu);
            $contenu->setClasseCourse($this);
        }
        return $this;
    }

    public function removeContenu(ClasseContenu $contenu): static
    {
        if ($this->contenus->removeElement($contenu)) {
            if ($contenu->getClasseCourse() === $this) {
                $contenu->setClasseCourse(null);
            }
        }
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(bool $isHidden): static
    {
        $this->isHidden = $isHidden;
        return $this;
    }
}
