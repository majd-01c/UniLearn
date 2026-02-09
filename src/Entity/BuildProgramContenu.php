<?php

namespace App\Entity;

use App\Repository\BuildProgramContenuRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuildProgramContenuRepository::class)]
#[ORM\UniqueConstraint(name: 'build_prog_contenu_unique', columns: ['build_program_course_id', 'contenu_id'])]
#[ORM\Index(columns: ['build_program_course_id'])]
#[ORM\Index(columns: ['contenu_id'])]
class BuildProgramContenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BuildProgramCourse::class, inversedBy: 'contenus')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BuildProgramCourse $buildProgramCourse = null;

    #[ORM\ManyToOne(targetEntity: Contenu::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Contenu $contenu = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuildProgramCourse(): ?BuildProgramCourse
    {
        return $this->buildProgramCourse;
    }

    public function setBuildProgramCourse(?BuildProgramCourse $buildProgramCourse): static
    {
        $this->buildProgramCourse = $buildProgramCourse;
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
