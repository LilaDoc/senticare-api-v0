<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\DeclarationRepository;

/**
 * Statistiques agrégées du tableau de bord superviseur (CDC §4.6).
 *
 * Règle blameless impérative : agrégation par service/type/période UNIQUEMENT,
 * jamais par individu/déclarant (CDC §2.1, §4.6).
 */
class StatsManager
{
    public function __construct(
        private readonly DeclarationRepository $declarationRepository,
        private readonly DeclarationManager $declarationManager,
    ) {
    }

    /**
     * @param array{dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable} $filters
     *
     * @return array{parService: array, parType: array, parPeriode: array}
     */
    public function aggregate(User $requester, array $filters = []): array
    {
        // Périmètre résolu par DeclarationManager — même règle métier que
        // search() (UC-06), une seule source de vérité pour "qui voit quoi".
        $criteria = $this->declarationManager->resolvePerimeterCriteria($requester);

        // Règle blameless impérative (CDC §2.1/§4.6) : les statistiques ne
        // doivent JAMAIS pouvoir être scopées à un déclarant individuel.
        // resolvePerimeterCriteria() renvoie ce critère pour un simple
        // soignant (légitime pour search(), sa propre liste) — mais le
        // tableau de bord est réservé aux cadres/chefs de pôle (§4.6), donc
        // ce cas ne doit jamais atteindre l'agrégation.
        if (array_key_exists('declarant', $criteria)) {
            throw new \RuntimeException('Les statistiques ne peuvent jamais être scopées à un déclarant individuel (règle blameless, CDC §2.1).');
        }

        return $this->declarationRepository->aggregate($criteria, $filters);
    }
}
