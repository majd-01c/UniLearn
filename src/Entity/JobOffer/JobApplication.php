<?php

namespace App\Entity;

use App\Repository\JobApplicationRepository;
use App\Enum\JobApplicationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: JobApplicationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_offer_student', columns: ['offer_id', 'student_id'])]
#[Vich\Uploadable]
class JobApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JobOffer::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?JobOffer $offer = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'jobApplications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $student = null;

    #[Assert\Length(max: 5000)]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[Vich\UploadableField(mapping: 'cv_files', fileNameProperty: 'cvFileName')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        mimeTypesMessage: 'Please upload a valid document (PDF, DOC, or DOCX)'
    )]
    private ?File $cvFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cvFileName = null;

    #[ORM\Column(type: 'string', enumType: JobApplicationStatus::class)]
    private ?JobApplicationStatus $status = JobApplicationStatus::SUBMITTED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ATS Scoring Fields
    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $scoreBreakdown = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scoredAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extractedData = null;

    // Notification fields
    #[ORM\Column(nullable: true)]
    private ?bool $statusNotified = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $statusNotifiedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $statusMessage = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffer(): ?JobOffer
    {
        return $this->offer;
    }

    public function setOffer(?JobOffer $offer): static
    {
        $this->offer = $offer;
        return $this;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getCvFile(): ?File
    {
        return $this->cvFile;
    }

    public function setCvFile(?File $cvFile = null): void
    {
        $this->cvFile = $cvFile;

        if (null !== $cvFile) {
            $this->updatedAt = new \DateTime();
        }
    }

    public function getCvFileName(): ?string
    {
        return $this->cvFileName;
    }

    public function setCvFileName(?string $cvFileName): static
    {
        $this->cvFileName = $cvFileName;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getStatus(): ?JobApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(JobApplicationStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // ATS Getters and Setters

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getScoreBreakdown(): ?array
    {
        return $this->scoreBreakdown;
    }

    public function setScoreBreakdown(?array $scoreBreakdown): static
    {
        $this->scoreBreakdown = $scoreBreakdown;
        return $this;
    }

    public function getScoredAt(): ?\DateTimeImmutable
    {
        return $this->scoredAt;
    }

    public function setScoredAt(?\DateTimeImmutable $scoredAt): static
    {
        $this->scoredAt = $scoredAt;
        return $this;
    }

    public function getExtractedData(): ?array
    {
        return $this->extractedData;
    }

    public function setExtractedData(?array $extractedData): static
    {
        $this->extractedData = $extractedData;
        return $this;
    }

    // Notification getters and setters

    public function isStatusNotified(): ?bool
    {
        return $this->statusNotified;
    }

    public function setStatusNotified(?bool $statusNotified): static
    {
        $this->statusNotified = $statusNotified;
        return $this;
    }

    public function getStatusNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->statusNotifiedAt;
    }

    public function setStatusNotifiedAt(?\DateTimeImmutable $statusNotifiedAt): static
    {
        $this->statusNotifiedAt = $statusNotifiedAt;
        return $this;
    }

    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    public function setStatusMessage(?string $statusMessage): static
    {
        $this->statusMessage = $statusMessage;
        return $this;
    }

    /**
     * Check if this application has a decision (accepted or rejected)
     */
    public function hasDecision(): bool
    {
        return in_array($this->status, [JobApplicationStatus::ACCEPTED, JobApplicationStatus::REJECTED], true);
    }

    /**
     * Check if status change needs notification
     */
    public function needsStatusNotification(): bool
    {
        return $this->hasDecision() && !$this->statusNotified;
    }
}
