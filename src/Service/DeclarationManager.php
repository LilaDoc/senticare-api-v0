<?php

namespace App\Service;

use App\Entity\Declaration;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Repository\DeclarationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier du cycle de vie d'une déclaration d'EI (CDC §4.2, §4.3, §4.4).
 */
class DeclarationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeclarationRepository $declarationRepository,
        private readonly NotificationManager $notificationManager,
        private readonly SuggestionRmmManager $suggestionRmmManager,
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
    ): Declaration {
        // TODO: valider dateConstat <= now (CDC §4.3 — "ne peut pas être dans le futur"),
        // instancier Declaration (statut = Brouillon par défaut dans le constructeur),
        // setDeclarant/setService/setTypeEI/setDateConstat/setDateSurvenue, persister, flush.
        throw new \RuntimeException('TODO: implement DeclarationManager::createDraft()');
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
        // TODO: vérifier que $declaration->getStatut()->estModifiable() est vrai (garde-fou métier,
        // en plus du contrôle d'accès fait par DeclarationVoter::EDIT en amont),
        // appliquer les setters concernés depuis $changes, flush.
        throw new \RuntimeException('TODO: implement DeclarationManager::updateDraft()');
    }

    /**
     * Abandonne une déclaration (UC-04) — irréversible, uniquement depuis Brouillon.
     */
    public function abandon(Declaration $declaration): void
    {
        // TODO: vérifier que StatutEnum::Abandonnee figure dans
        // $declaration->getStatut()->transitionsAutorisees(), sinon lever une exception métier.
        // $declaration->setStatut(StatutEnum::Abandonnee); flush().
        throw new \RuntimeException('TODO: implement DeclarationManager::abandon()');
    }

    /**
     * Soumet une déclaration (UC-05) — verrouille le contenu, déclenche la notification
     * (UC-09) et la génération automatique de la fiche RMM si isEIGS (UC-11, CDC §4.5).
     */
    public function submit(Declaration $declaration): void
    {
        // TODO:
        // 1. vérifier la transition Brouillon -> Soumise (StatutEnum::transitionsAutorisees())
        // 2. $declaration->setStatut(StatutEnum::Soumise) (met aussi à jour submittedAt)
        // 3. si $declaration->isEIGS() : $this->suggestionRmmManager->generateAuto($declaration)
        // 4. $this->notificationManager->notifySubmission($declaration)
        //    -> ne doit JAMAIS bloquer la soumission en cas d'échec d'envoi (US-3.2 / CDC §6.3)
        // 5. flush()
        throw new \RuntimeException('TODO: implement DeclarationManager::submit()');
    }

    /**
     * Change le statut d'une déclaration (cadre / chef de pôle) — en_analyse, cloturee (CDC §4.4).
     */
    public function changeStatut(Declaration $declaration, StatutEnum $nouveauStatut): void
    {
        // TODO: vérifier que $nouveauStatut figure dans
        // $declaration->getStatut()->transitionsAutorisees(), sinon lever une exception métier.
        throw new \RuntimeException('TODO: implement DeclarationManager::changeStatut()');
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
        // TODO: filtrage périmètre selon rôle (soignant: ses propres déclarations ;
        // cadre: son/ses service(s) ; chef de pôle: son pôle), toujours côté serveur
        // (CDC §6.2 — jamais de filtrage côté client), puis appliquer les $filters.
        throw new \RuntimeException('TODO: implement DeclarationManager::search()');
    }
}
