<?php

namespace App\Entity;

use App\Repository\ContenuRepository;
use App\Enum\ContenuType;
use App\Enum\FileType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ContenuRepository::class)]
#[ORM\Index(columns: ['type'])]
#[ORM\Index(columns: ['published'])]
#[Vich\Uploadable]
class Contenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Vich\UploadableField(mapping: 'content_files', fileNameProperty: 'fileName', size: 'fileSize')]
    #[Assert\File(
        maxSize: '50M',
        mimeTypes: [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'video/mp4',
            'video/webm',
            'video/ogg',
            'video/quicktime',
            'audio/mpeg',
            'audio/ogg',
            'audio/wav',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],
        mimeTypesMessage: 'Please upload a valid file (PDF, Word, PPT, Excel, Video, Audio, or Image)'
    )]
    private ?File $contentFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

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

    public function getContentFile(): ?File
    {
        return $this->contentFile;
    }

    public function setContentFile(?File $contentFile = null): void
    {
        $this->contentFile = $contentFile;

        if (null !== $contentFile) {
            $this->updatedAt = new \DateTime();
        }
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
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
