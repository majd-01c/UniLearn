<?php

namespace App\Entity;

use App\Repository\ContenuRepository;
use App\Enum\ContenuType;
use App\Enum\FileType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContenuRepository::class)]
#[ORM\Index(columns: ['type'])]
#[ORM\Index(columns: ['published'])]
class Contenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileUrl = null;

    #[ORM\Column(type: 'string', enumType: FileType::class, nullable: true)]
    private ?FileType $fileType = null;

    #[ORM\Column(type: 'string', enumType: ContenuType::class)]
    private ?ContenuType $type = null;

    #[ORM\Column]
    private bool $published = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, CourseContenu>
     */
    #[ORM\OneToMany(targetEntity: CourseContenu::class, mappedBy: 'contenu', orphanRemoval: true)]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
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

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): static
    {
        $this->fileUrl = $fileUrl;
        return $this;
    }

    public function getFileType(): ?FileType
    {
        return $this->fileType;
    }

    public function setFileType(?FileType $fileType): static
    {
        $this->fileType = $fileType;
        return $this;
    }

    public function getType(): ?ContenuType
    {
        return $this->type;
    }

    public function setType(ContenuType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;
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
     * @return Collection<int, CourseContenu>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(CourseContenu $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setContenu($this);
        }
        return $this;
    }

    public function removeCourse(CourseContenu $course): static
    {
        if ($this->courses->removeElement($course)) {
            if ($course->getContenu() === $this) {
                $course->setContenu(null);
            }
        }
        return $this;
    }
}
