<?php

namespace App\Entity;

use App\Repository\SuggestionRmmRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Fiche de Revue de Mortalité et Morbidité (RMM).
 *
 * Générée automatiquement par le système à la soumission d'un EIGS (isAuto = true).
 * Suggérée volontairement par le déclarant pour les EI non-EIGS (isAuto = false).
 * Non suppressible pour les EIGS (CDC §4.5).
 */
#[ORM\Entity(repositoryClass: SuggestionRmmRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SuggestionRmm
{
    // UUID v4 — empêche l'énumération des ressources (CDC §6.2)
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /**
     * Ordre du jour pré-rédigé dans un esprit blameless (CDC §4.5).
     * Généré automatiquement par le service métier — centré sur le processus.
     */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $ordreJour = null;

    /**
     * true = générée automatiquement par le système (EIGS).
     * false = suggérée volontairement par le déclarant.
     */
    #[ORM\Column]
    private bool $isAuto = false;

    /**
     * Côté propriétaire de la relation OneToOne.
     * La déclaration est liée à cette fiche via Declaration::ficheRMM (mappedBy).
     */
    #[ORM\OneToOne(targetEntity: Declaration::class, inversedBy: 'ficheRMM')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Declaration $declaration = null;

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

    public function getOrdreJour(): ?string
    {
        return $this->ordreJour;
    }

    public function setOrdreJour(string $ordreJour): static
    {
        $this->ordreJour = $ordreJour;

        return $this;
    }

    public function isAuto(): bool
    {
        return $this->isAuto;
    }

    public function setIsAuto(bool $isAuto): static
    {
        $this->isAuto = $isAuto;

        return $this;
    }

    public function getDeclaration(): ?Declaration
    {
        return $this->declaration;
    }

    public function setDeclaration(?Declaration $declaration): static
    {
        $this->declaration = $declaration;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}

