<?php

namespace App\Entity;

use App\Enum\RoleEnum;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Utilisateur de l'application SentiCare (CDC §5.1).
 *
 * Authentification JWT (LexikJWTBundle) — mot de passe haché Argon2id,
 * jamais stocké en clair (CDC §6.1). L'email est l'identifiant de connexion.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // -------------------------------------------------------------------------
    // Identifiant — UUID v4, empêche l'énumération des ressources (CDC §6.2)
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    // -------------------------------------------------------------------------
    // Identité réglementaire — signataire des déclarations (CDC §5.1)
    // -------------------------------------------------------------------------

    /** Identifiant de connexion. */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    /** Mot de passe haché Argon2id — jamais stocké en clair (CDC §6.1). */
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    // -------------------------------------------------------------------------
    // Rôles Symfony (CDC §3)
    // -------------------------------------------------------------------------

    /**
     * Un seul rôle métier par compte (CDC §3). getRoles() (imposée par
     * UserInterface, qui doit renvoyer un tableau) construit le tableau à la
     * volée à partir de cette valeur unique + ROLE_USER — voir plus bas.
     * La hiérarchie est configurée dans security.yaml.
     */
    #[ORM\Column(enumType: RoleEnum::class)]
    private ?RoleEnum $role = null;

    // -------------------------------------------------------------------------
    // État du compte
    // -------------------------------------------------------------------------

    /** Désactivation sans suppression — traçabilité conservée (CDC §5.1). */
    #[ORM\Column]
    private bool $isActive = true;

    /** Mis à jour à chaque connexion réussie (CDC §6.3 — journalisation des connexions). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    // -------------------------------------------------------------------------
    // Traçabilité du compte (CDC §5.1)
    // -------------------------------------------------------------------------

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Utilisateur qui a créé ce compte.
     * Chef de pôle pour soignants/cadres, Admin pour chefs de pôle.
     * Null uniquement pour le premier compte admin (bootstrap).
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $createdBy = null;

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * Un soignant peut être rattaché à plusieurs services (CDC §3).
     */
    #[ORM\ManyToMany(targetEntity: Service::class, inversedBy: 'users')]
    private Collection $services;

    #[ORM\OneToMany(targetEntity: Declaration::class, mappedBy: 'declarant')]
    private Collection $declarations;

    // -------------------------------------------------------------------------
    // Constructeur
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->services     = new ArrayCollection();
        $this->declarations = new ArrayCollection();
        $this->createdAt    = new \DateTimeImmutable();
        $this->updatedAt    = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // -------------------------------------------------------------------------
    // UserInterface / PasswordAuthenticatedUserInterface — requis par Symfony Security
    // -------------------------------------------------------------------------

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * UserInterface impose un tableau en sortie, indépendamment de notre
     * règle métier "un seul rôle par compte" (CDC §3) — construit ici à la
     * volée à partir de $role, jamais stocké tel quel. Symfony ajoute
     * toujours ROLE_USER. La hiérarchie (ROLE_CHEF_POLE hérite de
     * ROLE_CADRE, etc.) est dans security.yaml.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_unique([$this->role->value, 'ROLE_USER']);
    }

    public function getRole(): ?RoleEnum
    {
        return $this->role;
    }

    public function setRole(RoleEnum $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Reçoit un mot de passe déjà haché (Argon2id) — le hachage est effectué
     * par UserPasswordHasherInterface en amont (jamais dans l'entité).
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void {}

    // -------------------------------------------------------------------------
    // Getters / Setters
    // -------------------------------------------------------------------------

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

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

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

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

    public function getCreatedBy(): ?self
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?self $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        $this->services->removeElement($service);

        return $this;
    }

    /**
     * @return Collection<int, Declaration>
     */
    public function getDeclarations(): Collection
    {
        return $this->declarations;
    }

    public function addDeclaration(Declaration $declaration): static
    {
        if (!$this->declarations->contains($declaration)) {
            $this->declarations->add($declaration);
            $declaration->setDeclarant($this);
        }

        return $this;
    }

    public function removeDeclaration(Declaration $declaration): static
    {
        if ($this->declarations->removeElement($declaration)) {
            if ($declaration->getDeclarant() === $this) {
                $declaration->setDeclarant(null);
            }
        }

        return $this;
    }
}
