<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, ModuleCourse>
     */
    #[ORM\OneToMany(targetEntity: ModuleCourse::class, mappedBy: 'course', orphanRemoval: true)]
    private Collection $modules;

    /**
     * @var Collection<int, CourseContenu>
     */
    #[ORM\OneToMany(targetEntity: CourseContenu::class, mappedBy: 'course', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $contenus;

    public function __construct()
    {
        $this->modules = new ArrayCollection();
        $this->contenus = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * @return Collection<int, ModuleCourse>
     */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(ModuleCourse $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
            $module->setCourse($this);
        }
        return $this;
    }

    public function removeModule(ModuleCourse $module): static
    {
        if ($this->modules->removeElement($module)) {
            if ($module->getCourse() === $this) {
                $module->setCourse(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CourseContenu>
     */
    public function getContenus(): Collection
    {
        return $this->contenus;
    }

    public function addContenu(CourseContenu $contenu): static
    {
        if (!$this->contenus->contains($contenu)) {
            $this->contenus->add($contenu);
            $contenu->setCourse($this);
        }
        return $this;
    }

    public function removeContenu(CourseContenu $contenu): static
    {
        if ($this->contenus->removeElement($contenu)) {
            if ($contenu->getCourse() === $this) {
                $contenu->setCourse(null);
            }
        }
        return $this;
    }
}
