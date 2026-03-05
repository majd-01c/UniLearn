<?php

namespace App\Entity;

use App\Repository\ProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProgramRepository::class)]
#[ORM\Index(columns: ['published'])]
class Program
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Program name is required')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Name must be at least {{ limit }} characters')]
    private ?string $name = null;

    #[ORM\Column]
    private bool $published = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, ProgramModule>
     */
    #[ORM\OneToMany(targetEntity: ProgramModule::class, mappedBy: 'program', orphanRemoval: true)]
    private Collection $modules;

    /**
     * @var Collection<int, ProgramChatMessage>
     */
    #[ORM\OneToMany(targetEntity: ProgramChatMessage::class, mappedBy: 'program', orphanRemoval: true)]
    private Collection $chatMessages;

    public function __construct()
    {
        $this->modules = new ArrayCollection();
        $this->chatMessages = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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
     * @return Collection<int, ProgramModule>
     */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(ProgramModule $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
            $module->setProgram($this);
        }
        return $this;
    }

    public function removeModule(ProgramModule $module): static
    {
        if ($this->modules->removeElement($module)) {
            if ($module->getProgram() === $this) {
                $module->setProgram(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProgramChatMessage>
     */
    public function getChatMessages(): Collection
    {
        return $this->chatMessages;
    }

    public function addChatMessage(ProgramChatMessage $chatMessage): static
    {
        if (!$this->chatMessages->contains($chatMessage)) {
            $this->chatMessages->add($chatMessage);
            $chatMessage->setProgram($this);
        }
        return $this;
    }

    public function removeChatMessage(ProgramChatMessage $chatMessage): static
    {
        if ($this->chatMessages->removeElement($chatMessage)) {
            if ($chatMessage->getProgram() === $this) {
                $chatMessage->setProgram(null);
            }
        }
        return $this;
    }
}
