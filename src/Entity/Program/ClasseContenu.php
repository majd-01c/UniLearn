<?php

namespace App\Entity;

use App\Repository\ClasseContenuRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClasseContenuRepository::class)]
#[ORM\UniqueConstraint(name: 'classe_contenu_unique', columns: ['classe_course_id', 'contenu_id'])]
#[ORM\Index(columns: ['classe_course_id'])]
#[ORM\Index(columns: ['contenu_id'])]
class ClasseContenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClasseCourse::class, inversedBy: 'contenus')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClasseCourse $classeCourse = null;

    #[ORM\ManyToOne(targetEntity: Contenu::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Contenu $contenu = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClasseCourse(): ?ClasseCourse
    {
        return $this->classeCourse;
    }

    public function setClasseCourse(?ClasseCourse $classeCourse): static
    {
        $this->classeCourse = $classeCourse;
        return $this;
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
}
