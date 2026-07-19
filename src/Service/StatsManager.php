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
    ) {
    }

    /**
     * @param array{dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable} $filters
     *
     * @return array{parService: array, parType: array, parPeriode: array}
     */
    public function aggregate(User $requester, array $filters = []): array
    {
        // TODO: requêtes agrégées (GROUP BY service / type / période) scopées au
        // périmètre de $requester (cadre: son service ; chef de pôle: son pôle).
        // Ne JAMAIS grouper par déclarant/soignant (règle blameless — CDC §2.1).
        throw new \RuntimeException('TODO: implement StatsManager::aggregate()');
    }
}
