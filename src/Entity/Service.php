<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Service médical rattaché à un pôle (CDC §1.1 — 9 services).
 * Créé et géré par ROLE_ADMIN (UC-12) ou ROLE_CHEF_POLE (UC-10).
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Service
{
    // UUID v4 — empêche l'énumération des ressources (CDC §6.2)
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    /** Désactivation sans suppression — traçabilité conservée (CDC §5.1, UC-12). */
    #[ORM\Column]
    private bool $isActive = true;

    /** Pôle auquel appartient ce service. */
    #[ORM\ManyToOne(targetEntity: Pole::class, inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Pole $pole = null;

    /** Utilisateurs rattachés à ce service (soignants, cadres). */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'services')]
    private Collection $users;

    #[ORM\OneToMany(targetEntity: Declaration::class, mappedBy: 'service')]
    private Collection $declarations;

    /** Chef de pôle ou admin qui a créé ce service. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->users        = new ArrayCollection();
        $this->declarations = new ArrayCollection();
        $this->createdAt    = new \DateTimeImmutable();
        $this->updatedAt    = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getPole(): ?Pole
    {
        return $this->pole;
    }

    public function setPole(?Pole $pole): static
    {
        $this->pole = $pole;

        return $this;
    }

    /** @return Collection<int, User> */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->addService($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            $user->removeService($this);
        }

        return $this;
    }

    /** @return Collection<int, Declaration> */
    public function getDeclarations(): Collection
    {
        return $this->declarations;
    }

    public function addDeclaration(Declaration $declaration): static
    {
        if (!$this->declarations->contains($declaration)) {
            $this->declarations->add($declaration);
            $declaration->setService($this);
        }

        return $this;
    }

    public function removeDeclaration(Declaration $declaration): static
    {
        if ($this->declarations->removeElement($declaration)) {
            if ($declaration->getService() === $this) {
                $declaration->setService(null);
            }
        }

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

