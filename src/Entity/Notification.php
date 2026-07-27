<?php

namespace App\Entity;

use App\Enum\NotificationStatusEnum;
use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Trace d'une notification email envoyée à un superviseur (CDC §5.1, UC-08/UC-09).
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    // UUID v4 — empêche l'énumération des ressources (CDC §6.2)
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $destinataire = null;

    #[ORM\ManyToOne(targetEntity: Declaration::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Declaration $declaration = null;

    /** Renseigné uniquement si l'envoi a réussi. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(enumType: NotificationStatusEnum::class, nullable: true)]
    private ?NotificationStatusEnum $status = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDestinataire(): ?User
    {
        return $this->destinataire;
    }

    public function setDestinataire(User $destinataire): static
    {
        $this->destinataire = $destinataire;

        return $this;
    }

    public function getDeclaration(): ?Declaration
    {
        return $this->declaration;
    }

    public function setDeclaration(Declaration $declaration): static
    {
        $this->declaration = $declaration;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getStatus(): ?NotificationStatusEnum
    {
        return $this->status;
    }

    public function setStatus(NotificationStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }
}
