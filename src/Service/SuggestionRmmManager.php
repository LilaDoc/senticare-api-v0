<?php

namespace App\Service;

use App\Entity\Declaration;
use App\Entity\SuggestionRmm;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Génération des fiches RMM (CDC §4.5, UC-11).
 */
class SuggestionRmmManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Génération automatique et obligatoire pour tout EIGS à la soumission (CDC §4.5).
     * Le déclarant ne peut pas désactiver cette génération.
     */
    public function generateAuto(Declaration $declaration): SuggestionRmm
    {
        // TODO: construire l'ordre du jour blameless (gravité, date, service, nom du
        // déclarant — exigence réglementaire de signature, CDC §4.5), isAuto = true,
        // persister, lier à $declaration (OneToOne), flush.
        throw new \RuntimeException('TODO: implement SuggestionRmmManager::generateAuto()');
    }

    /**
     * Suggestion volontaire par le déclarant pour un EI non-EIGS (CDC §4.5).
     */
    public function generateManuelle(Declaration $declaration): SuggestionRmm
    {
        // TODO: même structure d'ordre du jour, isAuto = false.
        throw new \RuntimeException('TODO: implement SuggestionRmmManager::generateManuelle()');
    }
}
