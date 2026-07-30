<?php

namespace App\Entity;

use App\Enum\LogTypeEnum;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entrée du journal d'audit (CDC §6.3, UC-11) — consultable par ROLE_ADMIN
 * uniquement. Trace les connexions, soumissions et actions sensibles
 * (gestion des comptes/pôles/services).
 */
#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
class LogEntry
{
    // UUID v4 — empêche l'énumération des ressources (CDC §6.2)
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(enumType: LogTypeEnum::class)]
    private ?LogTypeEnum $type = null;

    /**
     * Nullable : un login échoué sur un email inconnu ne correspond à aucun
     * compte — on trace quand même la tentative, sans utilisateur associé.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getType(): ?LogTypeEnum
    {
        return $this->type;
    }

    public function setType(LogTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
