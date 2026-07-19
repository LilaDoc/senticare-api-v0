<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SuggestionRmmRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Consultation des fiches RMM du périmètre (CDC §4.5, US-3.3).
 */
#[Route('/api/rmm')]
#[IsGranted('ROLE_CADRE')]
class RmmController extends AbstractController
{
    public function __construct(
        private readonly SuggestionRmmRepository $suggestionRmmRepository,
    ) {
    }

    #[Route('', name: 'rmm_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        // TODO: filtrer par périmètre (service pour cadre, pôle pour chef de pôle)
        // via la déclaration liée (SuggestionRmm::getDeclaration()) — jamais toutes les fiches.
        throw new \RuntimeException('TODO: implement RmmController::list()');
    }

    #[Route('/{id}', name: 'rmm_show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: charger (404 sinon), vérifier le périmètre via la déclaration liée.
        throw new \RuntimeException('TODO: implement RmmController::show()');
    }
}
