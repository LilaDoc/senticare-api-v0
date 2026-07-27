<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StatsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Statistiques du tableau de bord superviseur (CDC §4.6, US-3.1).
 */
#[Route('/api/dashboard')]
#[IsGranted('ROLE_CADRE')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly StatsManager $statsManager,
    ) {
    }

    #[Route('/stats', name: 'dashboard_stats', methods: ['GET'])]
    public function stats(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        
        // TODO: extraire les filtres de période depuis la query string,
        // déléguer à StatsManager::aggregate($user, $filters).
        throw new \RuntimeException('TODO: implement DashboardController::stats()');
    }
}
