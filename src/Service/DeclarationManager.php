<?php

namespace App\Service;

use App\Entity\Declaration;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Repository\DeclarationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Logique métier du cycle de vie d'une déclaration d'EI (CDC §4.2, §4.3, §4.4).
 */
class DeclarationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeclarationRepository $declarationRepository,
        private readonly NotificationManager $notificationManager,
        private readonly LogManager $logManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Crée une déclaration en statut brouillon (UC-01/UC-02).
     */
    public function createDraft(
        User $declarant,
        ServiceEntity $service,
        TypeEIEnum $typeEI,
        \DateTimeImmutable $dateConstat,
        \DateTimeImmutable $dateSurvenue,
        bool $deces,
        bool $pronosticVitalEnJeu,
        bool $risqueDeficitFonctionnelPermanent,
        ?GraviteEnum $choixSiNonEIGS = null,
    ): Declaration {
        if ($dateConstat > new \DateTimeImmutable()) {
            throw new \RuntimeException('La date de constat ne peut pas être dans le futur.');
        }

        if (!$declarant->getServices()->contains($service)) {
            throw new \RuntimeException('Ce service n\'est pas autorisé pour ce déclarant.');
        }

        $gravite = $this->calculerGravite($deces, $pronosticVitalEnJeu, $risqueDeficitFonctionnelPermanent, $choixSiNonEIGS);

        $declaration = new Declaration();
        $declaration->setDeclarant($declarant);
        $declaration->setService($service);
        $declaration->setTypeEI($typeEI);
        $declaration->setDateConstat($dateConstat);
        $declaration->setDateSurvenue($dateSurvenue);
        $declaration->setGravite($gravite);
        // setStatut(Brouillon) inutile : c'est déjà la valeur par défaut posée
        // par le constructeur de Declaration.

        $this->entityManager->persist($declaration);
        $this->entityManager->flush();

        return $declaration;
    }

    /**
     * Assistant de qualification de la gravité en 3 questions (CDC §4.2 — formulaire HAS EIGS).
     *
     * Si aucune des 3 questions EIGS n'est positive, le choix entre Mineur et Modéré
     * n'est pas déductible automatiquement : il doit être fourni explicitement.
     */
    public function calculerGravite(
        bool $deces,
        bool $pronosticVitalEnJeu,
        bool $risqueDeficitFonctionnelPermanent,
        ?GraviteEnum $choixSiNonEIGS = null,
    ): GraviteEnum {
        return match (true) {
            $deces => GraviteEnum::Deces,
            $pronosticVitalEnJeu => GraviteEnum::Critique,
            $risqueDeficitFonctionnelPermanent => GraviteEnum::Grave,
            null !== $choixSiNonEIGS && in_array($choixSiNonEIGS, [GraviteEnum::Mineur, GraviteEnum::Modere], true) => $choixSiNonEIGS,
            default => throw new \InvalidArgumentException('Gravité Mineur ou Modéré à préciser explicitement lorsqu\'aucune question EIGS n\'est positive.'),
        };
    }

    /**
     * Met à jour un brouillon existant (UC-03).
     *
     * @param array<string, mixed> $changes
     */
    public function updateDraft(Declaration $declaration, array $changes): Declaration
    {
        // Garde-fou métier : le "qui a le droit" est déjà vérifié par
        // DeclarationVoter::EDIT dans le contrôleur, avant d'arriver ici. Ici on
        // vérifie autre chose : "est-ce que cette action a un sens ?" (CDC §4.4 —
        // seul un brouillon est modifiable, quel que soit l'utilisateur).
        if (!$declaration->getStatut()->estModifiable()) {
            throw new \RuntimeException('Cette déclaration n\'est plus modifiable (statut différent de brouillon).');
        }

        // Champs volontairement exclus : gravite/isEIGS (verrouillés, calculés par
        // calculerGravite() + Declaration::setGravite() — CDC §4.2) et service
        // (pré-rempli à la création, non ré-modifiable ici).
        if (array_key_exists('dateConstat', $changes)) {
            // CDC §4.3 : même règle qu'à la création (createDraft()) — un
            // brouillon modifié ne doit pas non plus pouvoir se retrouver
            // avec une date de constat dans le futur.
            if ($changes['dateConstat'] > new \DateTimeImmutable()) {
                throw new \RuntimeException('La date de constat ne peut pas être dans le futur.');
            }
            $declaration->setDateConstat($changes['dateConstat']);
        }
        if (array_key_exists('dateSurvenue', $changes)) {
            $declaration->setDateSurvenue($changes['dateSurvenue']);
        }
        if (array_key_exists('lieuDifferent', $changes)) {
            $declaration->setLieuDifferent($changes['lieuDifferent']);
        }
        if (array_key_exists('lieuDifferentDetail', $changes)) {
            $declaration->setLieuDifferentDetail($changes['lieuDifferentDetail']);
        }
        if (array_key_exists('typeEI', $changes)) {
            $declaration->setTypeEI($changes['typeEI']);
        }
        if (array_key_exists('description', $changes)) {
            // CDC §4.3 : minimum 20 caractères — contrainte déclarée sur
            // Declaration::$description (#[Assert\Length]), validée ici sans
            // avoir besoin d'une instance de Declaration entièrement valide.
            $violations = $this->validator->validatePropertyValue(Declaration::class, 'description', $changes['description']);
            if (count($violations) > 0) {
                throw new \RuntimeException($violations[0]->getMessage());
            }
            $declaration->setDescription($changes['description']);
        }
        if (array_key_exists('consequencesAutres', $changes)) {
            $declaration->setConsequencesAutres($changes['consequencesAutres']);
        }
        if (array_key_exists('consequencesAutresDetail', $changes)) {
            $declaration->setConsequencesAutresDetail($changes['consequencesAutresDetail']);
        }
        if (array_key_exists('mesuresImmediatesPatient', $changes)) {
            $declaration->setMesuresImmediatesPatient($changes['mesuresImmediatesPatient']);
        }
        if (array_key_exists('mesuresImmediatesPatientDetail', $changes)) {
            $declaration->setMesuresImmediatesPatientDetail($changes['mesuresImmediatesPatientDetail']);
        }
        if (array_key_exists('mesuresImmediatesProches', $changes)) {
            $declaration->setMesuresImmediatesProches($changes['mesuresImmediatesProches']);
        }
        if (array_key_exists('autresMesures', $changes)) {
            $declaration->setAutresMesures($changes['autresMesures']);
        }

        // CDC §4.3 : trois champs texte "conditionnels si Oui" — vérifiés sur
        // l'état final de l'entité (pas seulement sur $changes), pour rester
        // cohérent même si le booléen a été mis à true lors d'un appel
        // précédent et que seul le détail est fourni maintenant (ou l'inverse).
        if ($declaration->isLieuDifferent() && !$declaration->getLieuDifferentDetail()) {
            throw new \RuntimeException('Merci de préciser le lieu si celui-ci diffère du lieu de constat.');
        }
        if ($declaration->getConsequencesAutres() && !$declaration->getConsequencesAutresDetail()) {
            throw new \RuntimeException('Merci de préciser les conséquences pour les autres personnes concernées.');
        }
        if ($declaration->getMesuresImmediatesPatient() && !$declaration->getMesuresImmediatesPatientDetail()) {
            throw new \RuntimeException('Merci de préciser les mesures immédiates prises pour le patient.');
        }

        $this->entityManager->flush();

        return $declaration;
    }

    /**
     * Abandonne une déclaration (UC-04) — irréversible, uniquement depuis Brouillon.
     */
    public function abandon(Declaration $declaration): void
    {
        if (!in_array(StatutEnum::Abandonnee, $declaration->getStatut()->transitionsAutorisees(), true)) {
            throw new \RuntimeException('Transition non autorisée depuis ce statut.');
        }

        $declaration->setStatut(StatutEnum::Abandonnee);

        $this->entityManager->flush();
    }

    /**
     * Soumet une déclaration (UC-05) — verrouille le contenu et déclenche la
     * notification (UC-09). La génération de fiche RMM est hors périmètre V1
     * (CDC §4.5).
     */
    public function submit(Declaration $declaration): void
    {
        if (!in_array(StatutEnum::Soumise, $declaration->getStatut()->transitionsAutorisees(), true)) {
            throw new \RuntimeException('Transition non autorisée depuis ce statut.');
        }

        // setStatut(Soumise) met aussi submittedAt à jour automatiquement
        // (cf. Declaration::setStatut()) — pas besoin de le faire ici.
        $declaration->setStatut(StatutEnum::Soumise);

        // NotificationManager::notifySubmission() garantit ne jamais lever
        // d'exception (log interne en cas d'échec) — CDC §6.3 / US-3.2.
        $this->notificationManager->notifySubmission($declaration);

        $this->entityManager->flush();

        $this->logManager->log(
            LogTypeEnum::DeclarationSubmitted,
            $declaration->getDeclarant(),
            sprintf('Déclaration %s soumise par %s', $declaration->getId(), $declaration->getDeclarant()->getEmail())
        );
    }

    /**
     * Change le statut d'une déclaration (cadre / chef de pôle) — en_analyse, cloturee (CDC §4.4).
     *
     * Ne gère PAS la transition vers Soumise : c'est le rôle exclusif de submit(),
     * réservé au déclarant (DeclarationVoter::SUBMIT). Un cadre/chef de pôle qui
     * passerait StatutEnum::Soumise ici contournerait cette règle de périmètre.
     */
    public function changeStatut(Declaration $declaration, StatutEnum $nouveauStatut): void
    {
        if (!in_array($nouveauStatut, $declaration->getStatut()->transitionsAutorisees(), true)) {
            throw new \RuntimeException('Transition non autorisée depuis ce statut.');
        }

        $declaration->setStatut($nouveauStatut);

        $this->entityManager->flush();
    }

    /**
     * Liste filtrée pour le tableau de bord superviseur (UC-06, CDC §4.6).
     *
     * @param array{statut?: StatutEnum, dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable, typeEI?: TypeEIEnum, gravite?: GraviteEnum, eigsOnly?: bool, motCle?: string} $filters
     *
     * @return Declaration[]
     */
    public function search(User $requester, array $filters): array
    {
        return $this->declarationRepository->search($this->resolvePerimeterCriteria($requester), $filters);
    }

    /**
     * Résout le périmètre d'un superviseur en critères exploitables par le
     * repository — règle métier partagée par `search()` (UC-06) et
     * `StatsManager::aggregate()` (CDC §4.6) : "qui a le droit de voir quoi"
     * ne doit exister qu'à un seul endroit.
     *
     * @return array{pole?: ?\App\Entity\Pole, services?: array, declarant?: User}
     */
    public function resolvePerimeterCriteria(User $requester): array
    {
        if ($this->hasRole($requester, RoleEnum::ChefPole)) {
            // Convention (docs/ROADMAP.md) : le pôle d'un chef de pôle se déduit
            // de n'importe lequel de ses propres services.
            $firstService = $requester->getServices()->first() ?: null;

            return ['pole' => $firstService?->getPole()];
        }

        if ($this->hasRole($requester, RoleEnum::Cadre)) {
            return ['services' => $requester->getServices()->toArray()];
        }

        if ($this->hasRole($requester, RoleEnum::Admin)) {
            // CDC §3 : interdiction explicite, aucun accès aux déclarations.
            throw new \RuntimeException('Un administrateur n\'a aucun accès aux déclarations (CDC §3).');
        }

        if ($this->hasRole($requester, RoleEnum::Soignant)) {
            return ['declarant' => $requester];
        }

        throw new \RuntimeException('Rôle non autorisé à consulter les déclarations.');
    }

    private function hasRole(User $user, RoleEnum $role): bool
    {
        return in_array($role->value, $user->getRoles(), true);
    }
}
