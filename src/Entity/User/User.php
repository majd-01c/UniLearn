<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $role = 'STUDENT';

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePic = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $skills = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $about = null;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column]
    private ?bool $needsVerification = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emailVerificationCode = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $codeExpiryDate = null;

    #[ORM\Column]
    private bool $mustChangePassword = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tempPasswordGeneratedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(targetEntity: Profile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Profile $profile = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'createdBy', orphanRemoval: true)]
    private Collection $createdEvents;

    /**
     * @var Collection<int, EventParticipation>
     */
    #[ORM\OneToMany(targetEntity: EventParticipation::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $eventParticipations;

    /**
     * @var Collection<int, Assessment>
     */
    #[ORM\OneToMany(targetEntity: Assessment::class, mappedBy: 'teacher', orphanRemoval: true)]
    private Collection $assessmentsCreated;

    /**
     * @var Collection<int, Grade>
     */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'student', orphanRemoval: true)]
    private Collection $gradesAsStudent;

    /**
     * @var Collection<int, Grade>
     */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'teacher', orphanRemoval: true)]
    private Collection $gradesAsTeacher;

    /**
     * @var Collection<int, JobOffer>
     */
    #[ORM\OneToMany(targetEntity: JobOffer::class, mappedBy: 'partner', orphanRemoval: true)]
    private Collection $jobOffers;

    /**
     * @var Collection<int, JobApplication>
     */
    #[ORM\OneToMany(targetEntity: JobApplication::class, mappedBy: 'student', orphanRemoval: true)]
    private Collection $jobApplications;

    /**
     * @var Collection<int, GeneralChatMessage>
     */
    #[ORM\OneToMany(targetEntity: GeneralChatMessage::class, mappedBy: 'sender', orphanRemoval: true)]
    private Collection $generalChatMessages;

    /**
     * @var Collection<int, ProgramChatMessage>
     */
    #[ORM\OneToMany(targetEntity: ProgramChatMessage::class, mappedBy: 'sender', orphanRemoval: true)]
    private Collection $programChatMessages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->createdEvents = new \Doctrine\Common\Collections\ArrayCollection();
        $this->eventParticipations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->assessmentsCreated = new \Doctrine\Common\Collections\ArrayCollection();
        $this->gradesAsStudent = new \Doctrine\Common\Collections\ArrayCollection();
        $this->gradesAsTeacher = new \Doctrine\Common\Collections\ArrayCollection();
        $this->jobOffers = new \Doctrine\Common\Collections\ArrayCollection();
        $this->jobApplications = new \Doctrine\Common\Collections\ArrayCollection();
        $this->generalChatMessages = new \Doctrine\Common\Collections\ArrayCollection();
        $this->programChatMessages = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getProfilePic(): ?string
    {
        return $this->profilePic;
    }

    public function setProfilePic(?string $profilePic): static
    {
        $this->profilePic = $profilePic;
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

    public function getSkills(): ?array
    {
        return $this->skills ?? [];
    }

    public function setSkills(?array $skills): static
    {
        $this->skills = $skills;
        return $this;
    }

    public function getAbout(): ?string
    {
        return $this->about;
    }

    public function setAbout(?string $about): static
    {
        $this->about = $about;
        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isNeedsVerification(): ?bool
    {
        return $this->needsVerification;
    }

    public function setNeedsVerification(bool $needsVerification): static
    {
        $this->needsVerification = $needsVerification;
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    public function getEmailVerificationCode(): ?string
    {
        return $this->emailVerificationCode;
    }

    public function setEmailVerificationCode(?string $emailVerificationCode): static
    {
        $this->emailVerificationCode = $emailVerificationCode;
        return $this;
    }

    public function getCodeExpiryDate(): ?\DateTimeImmutable
    {
        return $this->codeExpiryDate;
    }

    public function setCodeExpiryDate(?\DateTimeImmutable $codeExpiryDate): static
    {
        $this->codeExpiryDate = $codeExpiryDate;
        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getCreatedEvents(): Collection
    {
        return $this->createdEvents;
    }

    public function addCreatedEvent(Event $event): static
    {
        if (!$this->createdEvents->contains($event)) {
            $this->createdEvents->add($event);
            $event->setCreatedBy($this);
        }
        return $this;
    }

    public function removeCreatedEvent(Event $event): static
    {
        if ($this->createdEvents->removeElement($event)) {
            if ($event->getCreatedBy() === $this) {
                $event->setCreatedBy(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, EventParticipation>
     */
    public function getEventParticipations(): Collection
    {
        return $this->eventParticipations;
    }

    public function addEventParticipation(EventParticipation $participation): static
    {
        if (!$this->eventParticipations->contains($participation)) {
            $this->eventParticipations->add($participation);
            $participation->setUser($this);
        }
        return $this;
    }

    public function removeEventParticipation(EventParticipation $participation): static
    {
        if ($this->eventParticipations->removeElement($participation)) {
            if ($participation->getUser() === $this) {
                $participation->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Assessment>
     */
    public function getAssessmentsCreated(): Collection
    {
        return $this->assessmentsCreated;
    }

    public function addAssessmentCreated(Assessment $assessment): static
    {
        if (!$this->assessmentsCreated->contains($assessment)) {
            $this->assessmentsCreated->add($assessment);
            $assessment->setTeacher($this);
        }
        return $this;
    }

    public function removeAssessmentCreated(Assessment $assessment): static
    {
        if ($this->assessmentsCreated->removeElement($assessment)) {
            if ($assessment->getTeacher() === $this) {
                $assessment->setTeacher(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Grade>
     */
    public function getGradesAsStudent(): Collection
    {
        return $this->gradesAsStudent;
    }

    public function addGradeAsStudent(Grade $grade): static
    {
        if (!$this->gradesAsStudent->contains($grade)) {
            $this->gradesAsStudent->add($grade);
            $grade->setStudent($this);
        }
        return $this;
    }

    public function removeGradeAsStudent(Grade $grade): static
    {
        if ($this->gradesAsStudent->removeElement($grade)) {
            if ($grade->getStudent() === $this) {
                $grade->setStudent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Grade>
     */
    public function getGradesAsTeacher(): Collection
    {
        return $this->gradesAsTeacher;
    }

    public function addGradeAsTeacher(Grade $grade): static
    {
        if (!$this->gradesAsTeacher->contains($grade)) {
            $this->gradesAsTeacher->add($grade);
            $grade->setTeacher($this);
        }
        return $this;
    }

    public function removeGradeAsTeacher(Grade $grade): static
    {
        if ($this->gradesAsTeacher->removeElement($grade)) {
            if ($grade->getTeacher() === $this) {
                $grade->setTeacher(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, JobOffer>
     */
    public function getJobOffers(): Collection
    {
        return $this->jobOffers;
    }

    public function addJobOffer(JobOffer $jobOffer): static
    {
        if (!$this->jobOffers->contains($jobOffer)) {
            $this->jobOffers->add($jobOffer);
            $jobOffer->setPartner($this);
        }
        return $this;
    }

    public function removeJobOffer(JobOffer $jobOffer): static
    {
        if ($this->jobOffers->removeElement($jobOffer)) {
            if ($jobOffer->getPartner() === $this) {
                $jobOffer->setPartner(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, JobApplication>
     */
    public function getJobApplications(): Collection
    {
        return $this->jobApplications;
    }

    public function addJobApplication(JobApplication $jobApplication): static
    {
        if (!$this->jobApplications->contains($jobApplication)) {
            $this->jobApplications->add($jobApplication);
            $jobApplication->setStudent($this);
        }
        return $this;
    }

    public function removeJobApplication(JobApplication $jobApplication): static
    {
        if ($this->jobApplications->removeElement($jobApplication)) {
            if ($jobApplication->getStudent() === $this) {
                $jobApplication->setStudent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, GeneralChatMessage>
     */
    public function getGeneralChatMessages(): Collection
    {
        return $this->generalChatMessages;
    }

    public function addGeneralChatMessage(GeneralChatMessage $message): static
    {
        if (!$this->generalChatMessages->contains($message)) {
            $this->generalChatMessages->add($message);
            $message->setSender($this);
        }
        return $this;
    }

    public function removeGeneralChatMessage(GeneralChatMessage $message): static
    {
        if ($this->generalChatMessages->removeElement($message)) {
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProgramChatMessage>
     */
    public function getProgramChatMessages(): Collection
    {
        return $this->programChatMessages;
    }

    public function addProgramChatMessage(ProgramChatMessage $message): static
    {
        if (!$this->programChatMessages->contains($message)) {
            $this->programChatMessages->add($message);
            $message->setSender($this);
        }
        return $this;
    }

    public function removeProgramChatMessage(ProgramChatMessage $message): static
    {
        if ($this->programChatMessages->removeElement($message)) {
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }
        return $this;
    }

    public function isMustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;
        return $this;
    }

    public function getTempPasswordGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->tempPasswordGeneratedAt;
    }

    public function setTempPasswordGeneratedAt(?\DateTimeImmutable $tempPasswordGeneratedAt): static
    {
        $this->tempPasswordGeneratedAt = $tempPasswordGeneratedAt;
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

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
    {
        // Unset the owning side of the relation if necessary
        if ($profile === null && $this->profile !== null) {
            $this->profile->setUser(null);
        }

        // Set the owning side of the relation if necessary
        if ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }

        $this->profile = $profile;
        return $this;
    }

    // UserInterface methods
    public function getRoles(): array
    {
        // Convert single role to Symfony's roles array format
        $roles = ['ROLE_' . strtoupper($this->role ?? 'STUDENT')];
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
}