<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
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
        // GET n'a pas de body : les filtres viennent de la query string,
        // comme DeclarationController::list() (mêmes clés, CDC §4.6).
        // tryFrom() (jamais from()) : une valeur invalide dans l'URL doit
        // renvoyer un 400 propre, pas un ValueError non capturé (500).
        $query = $request->query;
        $filters = [];

        if ($statutValue = $query->get('statut')) {
            $statut = StatutEnum::tryFrom($statutValue);
            if (!$statut) {
                return $this->json(['error' => 'Statut invalide.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $filters['statut'] = $statut;
        }

        if ($typeEIValue = $query->get('typeEI')) {
            $typeEI = TypeEIEnum::tryFrom($typeEIValue);
            if (!$typeEI) {
                return $this->json(['error' => 'Type d\'EI invalide.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $filters['typeEI'] = $typeEI;
        }

        if ($graviteValue = $query->get('gravite')) {
            $gravite = GraviteEnum::tryFrom($graviteValue);
            if (!$gravite) {
                return $this->json(['error' => 'Gravité invalide.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $filters['gravite'] = $gravite;
        }

        try {
            if ($dateFromValue = $query->get('dateFrom')) {
                $filters['dateFrom'] = new \DateTimeImmutable($dateFromValue);
            }
            if ($dateToValue = $query->get('dateTo')) {
                $filters['dateTo'] = new \DateTimeImmutable($dateToValue);
            }
        } catch (\Exception) {
            return $this->json(['error' => 'Date invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($query->getBoolean('eigsOnly')) {
            $filters['eigsOnly'] = true;
        }

        return $this->json($this->statsManager->aggregate($user, $filters));
    }
}
