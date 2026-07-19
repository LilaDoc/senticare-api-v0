<?php

namespace App\Entity;

use App\Enum\GraviteEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Repository\DeclarationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Déclaration d'un événement indésirable (EI).
 *
 * Cycle de vie géré par StatutEnum — statut initial : brouillon.
 * La gravité est calculée par l'assistant HAS (CDC §4.2) et verrouillée
 * après validation. isEIGS est dérivé automatiquement de la gravité.
 */
#[ORM\Entity(repositoryClass: DeclarationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Declaration
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
    // Dates (CDC §4.3)
    // -------------------------------------------------------------------------

    /** Date et heure de constat — ne peut pas être dans le futur. */
    #[ORM\Column]
    private ?\DateTimeImmutable $dateConstat = null;

    /** Date et heure de survenue — peut être antérieure au constat. */
    #[ORM\Column]
    private ?\DateTimeImmutable $dateSurvenue = null;

    // -------------------------------------------------------------------------
    // Lieu (CDC §4.3)
    // -------------------------------------------------------------------------

    #[ORM\Column]
    private bool $lieuDifferent = false;

    /** Précision textuelle si lieuDifferent = true. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lieuDifferentDetail = null;

    // -------------------------------------------------------------------------
    // Classification — stockés comme strings via backed enums (pas de JOIN)
    // -------------------------------------------------------------------------

    /**
     * Type d'EI choisi dans le formulaire wizard.
     * Doctrine stocke la valeur string de l'enum (ex: 'chute').
     */
    #[ORM\Column(enumType: TypeEIEnum::class)]
    private ?TypeEIEnum $typeEI = null;

    /**
     * Gravité calculée par l'assistant HAS en 3 questions (CDC §4.2).
     * Verrouillée en écriture après validation — jamais saisie manuellement.
     * Doctrine stocke la valeur string (ex: 'grave').
     */
    #[ORM\Column(enumType: GraviteEnum::class, nullable: true)]
    private ?GraviteEnum $gravite = null;

    /**
     * Vrai si gravite IN (grave, critique, deces).
     * Calculé automatiquement dans setGravite() — jamais saisi manuellement.
     */
    #[ORM\Column]
    private bool $isEIGS = false;

    // -------------------------------------------------------------------------
    // Statut — cycle de vie (CDC §4.4)
    // -------------------------------------------------------------------------

    /**
     * Statut courant de la déclaration.
     * Initialisé à brouillon dans le constructeur.
     * Doctrine stocke la valeur string (ex: 'brouillon').
     */
    #[ORM\Column(enumType: StatutEnum::class)]
    private StatutEnum $statut;

    // -------------------------------------------------------------------------
    // Description (CDC §4.3)
    // -------------------------------------------------------------------------

    /** Qu'avez-vous constaté ? Minimum 20 caractères. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Y a-t-il des conséquences pour d'autres personnes ? */
    #[ORM\Column(nullable: true)]
    private ?bool $consequencesAutres = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $consequencesAutresDetail = null;

    /** Des mesures immédiates ont-elles été prises pour le patient ? */
    #[ORM\Column(nullable: true)]
    private ?bool $mesuresImmediatesPatient = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $mesuresImmediatesPatientDetail = null;

    /** Des mesures immédiates ont-elles été prises pour les proches ? */
    #[ORM\Column(nullable: true)]
    private ?bool $mesuresImmediatesProches = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $autresMesures = null;

    /**
     * Suggestion de RMM par le déclarant.
     * Auto = true et verrouillé si isEIGS = true (CDC §4.3).
     */
    #[ORM\Column]
    private bool $suggestionRMM = false;

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    #[ORM\ManyToOne(inversedBy: 'declarations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $declarant = null;

    #[ORM\ManyToOne(inversedBy: 'declarations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\OneToOne(mappedBy: 'declaration', cascade: ['persist', 'remove'])]
    private ?SuggestionRmm $ficheRMM = null;

    // -------------------------------------------------------------------------
    // Traçabilité
    // -------------------------------------------------------------------------

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Renseigné à la soumission (UC-05). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // -------------------------------------------------------------------------
    // Constructeur
    // -------------------------------------------------------------------------

    public function __construct()
    {
        // Statut initial obligatoire : brouillon (CDC §4.4)
        $this->statut    = StatutEnum::Brouillon;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // -------------------------------------------------------------------------
    // Getters / Setters
    // -------------------------------------------------------------------------

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDateConstat(): ?\DateTimeImmutable
    {
        return $this->dateConstat;
    }

    public function setDateConstat(\DateTimeImmutable $dateConstat): static
    {
        $this->dateConstat = $dateConstat;

        return $this;
    }

    public function getDateSurvenue(): ?\DateTimeImmutable
    {
        return $this->dateSurvenue;
    }

    public function setDateSurvenue(\DateTimeImmutable $dateSurvenue): static
    {
        $this->dateSurvenue = $dateSurvenue;

        return $this;
    }

    public function isLieuDifferent(): bool
    {
        return $this->lieuDifferent;
    }

    public function setLieuDifferent(bool $lieuDifferent): static
    {
        $this->lieuDifferent = $lieuDifferent;

        return $this;
    }

    public function getLieuDifferentDetail(): ?string
    {
        return $this->lieuDifferentDetail;
    }

    public function setLieuDifferentDetail(?string $lieuDifferentDetail): static
    {
        $this->lieuDifferentDetail = $lieuDifferentDetail;

        return $this;
    }

    public function getTypeEI(): ?TypeEIEnum
    {
        return $this->typeEI;
    }

    public function setTypeEI(TypeEIEnum $typeEI): static
    {
        $this->typeEI = $typeEI;

        return $this;
    }

    public function getGravite(): ?GraviteEnum
    {
        return $this->gravite;
    }

    /**
     * Définit la gravité ET recalcule isEIGS automatiquement (CDC §4.3).
     * Met aussi à jour suggestionRMM si EIGS (auto-coché et verrouillé).
     */
    public function setGravite(GraviteEnum $gravite): static
    {
        $this->gravite  = $gravite;
        $this->isEIGS   = $gravite->isEIGS();

        // Auto-cocher suggestionRMM si EIGS (CDC §4.3)
        if ($this->isEIGS) {
            $this->suggestionRMM = true;
        }

        return $this;
    }

    public function isEIGS(): bool
    {
        return $this->isEIGS;
    }

    public function getStatut(): StatutEnum
    {
        return $this->statut;
    }

    public function setStatut(StatutEnum $statut): static
    {
        $this->statut = $statut;

        // Enregistrer la date de soumission lors du passage à "soumise"
        if ($statut === StatutEnum::Soumise && $this->submittedAt === null) {
            $this->submittedAt = new \DateTimeImmutable();
        }

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

    public function getConsequencesAutres(): ?bool
    {
        return $this->consequencesAutres;
    }

    public function setConsequencesAutres(?bool $consequencesAutres): static
    {
        $this->consequencesAutres = $consequencesAutres;

        return $this;
    }

    public function getConsequencesAutresDetail(): ?string
    {
        return $this->consequencesAutresDetail;
    }

    public function setConsequencesAutresDetail(?string $consequencesAutresDetail): static
    {
        $this->consequencesAutresDetail = $consequencesAutresDetail;

        return $this;
    }

    public function getMesuresImmediatesPatient(): ?bool
    {
        return $this->mesuresImmediatesPatient;
    }

    public function setMesuresImmediatesPatient(?bool $mesuresImmediatesPatient): static
    {
        $this->mesuresImmediatesPatient = $mesuresImmediatesPatient;

        return $this;
    }

    public function getMesuresImmediatesPatientDetail(): ?string
    {
        return $this->mesuresImmediatesPatientDetail;
    }

    public function setMesuresImmediatesPatientDetail(?string $mesuresImmediatesPatientDetail): static
    {
        $this->mesuresImmediatesPatientDetail = $mesuresImmediatesPatientDetail;

        return $this;
    }

    public function getMesuresImmediatesProches(): ?bool
    {
        return $this->mesuresImmediatesProches;
    }

    public function setMesuresImmediatesProches(?bool $mesuresImmediatesProches): static
    {
        $this->mesuresImmediatesProches = $mesuresImmediatesProches;

        return $this;
    }

    public function getAutresMesures(): ?string
    {
        return $this->autresMesures;
    }

    public function setAutresMesures(?string $autresMesures): static
    {
        $this->autresMesures = $autresMesures;

        return $this;
    }

    public function isSuggestionRMM(): bool
    {
        return $this->suggestionRMM;
    }

    public function setSuggestionRMM(bool $suggestionRMM): static
    {
        $this->suggestionRMM = $suggestionRMM;

        return $this;
    }

    public function getDeclarant(): ?User
    {
        return $this->declarant;
    }

    public function setDeclarant(?User $declarant): static
    {
        $this->declarant = $declarant;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getFicheRMM(): ?SuggestionRmm
    {
        return $this->ficheRMM;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

