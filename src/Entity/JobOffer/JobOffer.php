<?php

namespace App\Entity;

use App\Repository\JobOfferRepository;
use App\Enum\JobOfferType;
use App\Enum\JobOfferStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['type'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['location'])]
#[ORM\Index(columns: ['published_at'])]
#[ORM\Index(columns: ['expires_at'])]
class JobOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Assert\NotNull(message: 'Job type is required.')]
    #[ORM\Column(type: 'string', enumType: JobOfferType::class)]
    private ?JobOfferType $type = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[Assert\NotBlank(message: 'Description is required.')]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $requirements = null;

    // ATS Requirements Fields
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requiredSkills = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $preferredSkills = [];

    #[ORM\Column(nullable: true)]
    private ?int $minExperienceYears = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $minEducation = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requiredLanguages = [];

    #[ORM\Column(type: 'string', enumType: JobOfferStatus::class)]
    private ?JobOfferStatus $status = JobOfferStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'jobOffers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $partner = null;

    /**
     * @var Collection<int, JobApplication>
     */
    #[ORM\OneToMany(targetEntity: JobApplication::class, mappedBy: 'offer', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $applications;

    public function __construct()
    {
        $this->applications = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getType(): ?JobOfferType
    {
        return $this->type;
    }

    public function setType(JobOfferType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getRequirements(): ?string
    {
        return $this->requirements;
    }

    public function setRequirements(?string $requirements): static
    {
        $this->requirements = $requirements;
        return $this;
    }

    public function getStatus(): ?JobOfferStatus
    {
        return $this->status;
    }

    public function setStatus(JobOfferStatus $status): static
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getPartner(): ?User
    {
        return $this->partner;
    }

    public function setPartner(?User $partner): static
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @return Collection<int, JobApplication>
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(JobApplication $application): static
    {
        if (!$this->applications->contains($application)) {
            $this->applications->add($application);
            $application->setOffer($this);
        }

        return $this;
    }

    public function removeApplication(JobApplication $application): static
    {
        if ($this->applications->removeElement($application)) {
            if ($application->getOffer() === $this) {
                $application->setOffer(null);
            }
        }

        return $this;
    }

    // ATS Getters and Setters

    public function getRequiredSkills(): array
    {
        return $this->requiredSkills ?? [];
    }

    public function setRequiredSkills(?array $requiredSkills): static
    {
        $this->requiredSkills = $requiredSkills;
        return $this;
    }

    public function getPreferredSkills(): array
    {
        return $this->preferredSkills ?? [];
    }

    public function setPreferredSkills(?array $preferredSkills): static
    {
        $this->preferredSkills = $preferredSkills;
        return $this;
    }

    public function getMinExperienceYears(): ?int
    {
        return $this->minExperienceYears;
    }

    public function setMinExperienceYears(?int $minExperienceYears): static
    {
        $this->minExperienceYears = $minExperienceYears;
        return $this;
    }

    public function getMinEducation(): ?string
    {
        return $this->minEducation;
    }

    public function setMinEducation(?string $minEducation): static
    {
        $this->minEducation = $minEducation;
        return $this;
    }

    public function getRequiredLanguages(): array
    {
        return $this->requiredLanguages ?? [];
    }

    public function setRequiredLanguages(?array $requiredLanguages): static
    {
        $this->requiredLanguages = $requiredLanguages;
        return $this;
    }
}
