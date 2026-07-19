<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DeclarationRepository;
use App\Security\Voter\DeclarationVoter;
use App\Service\DeclarationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cycle de vie des déclarations d'EI (CDC §4.2 à §4.4, UC-01 à UC-07).
 *
 * ROLE_SOIGNANT est le rôle minimal requis (hiérarchie security.yaml : cadre et
 * chef de pôle en héritent). Le périmètre exact (mes déclarations / mon service /
 * mon pôle) est géré par DeclarationVoter, appelé explicitement dans chaque action.
 */
#[Route('/api/declarations')]
#[IsGranted('ROLE_SOIGNANT')]
class DeclarationController extends AbstractController
{
    public function __construct(
        private readonly DeclarationManager $declarationManager,
        private readonly DeclarationRepository $declarationRepository,
    ) {
    }

    #[Route('', name: 'declarations_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: extraire les filtres de la query string (statut, date, type, gravite,
        // eigsOnly, motCle — CDC §4.6), déléguer à DeclarationManager::search($user, $filters).
        throw new \RuntimeException('TODO: implement DeclarationController::list()');
    }

    #[Route('/{id}', name: 'declarations_show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: $declaration = $this->declarationRepository->find($id) ?? 404,
        // $this->denyAccessUnlessGranted(DeclarationVoter::VIEW, $declaration), sérialiser.
        throw new \RuntimeException('TODO: implement DeclarationController::show()');
    }

    #[Route('', name: 'declarations_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: décoder payload (service, typeEI, dateConstat, dateSurvenue,
        // réponses de l'assistant de gravité...), déléguer à
        // DeclarationManager::createDraft() puis ::calculerGravite(), 201.
        throw new \RuntimeException('TODO: implement DeclarationController::create()');
    }

    #[Route('/{id}', name: 'declarations_update', methods: ['PATCH'])]
    public function update(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: charger (404 sinon), $this->denyAccessUnlessGranted(DeclarationVoter::EDIT, $declaration),
        // DeclarationManager::updateDraft().
        throw new \RuntimeException('TODO: implement DeclarationController::update()');
    }

    #[Route('/{id}/abandon', name: 'declarations_abandon', methods: ['POST'])]
    public function abandon(string $id, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: charger, $this->denyAccessUnlessGranted(DeclarationVoter::ABANDON, $declaration),
        // DeclarationManager::abandon().
        throw new \RuntimeException('TODO: implement DeclarationController::abandon()');
    }

    #[Route('/{id}/submit', name: 'declarations_submit', methods: ['POST'])]
    public function submit(string $id, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: charger, $this->denyAccessUnlessGranted(DeclarationVoter::SUBMIT, $declaration),
        // DeclarationManager::submit(). Réponse : message de confirmation blameless (US-2.1).
        throw new \RuntimeException('TODO: implement DeclarationController::submit()');
    }

    #[Route('/{id}/statut', name: 'declarations_change_statut', methods: ['PATCH'])]
    public function changeStatut(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        // TODO: charger, $this->denyAccessUnlessGranted(DeclarationVoter::CHANGE_STATUT, $declaration),
        // décoder { statut } -> StatutEnum, DeclarationManager::changeStatut().
        throw new \RuntimeException('TODO: implement DeclarationController::changeStatut()');
    }

    #[Route('/{id}/pdf', name: 'declarations_pdf', methods: ['GET'])]
    public function exportPdf(string $id, #[CurrentUser] User $user): Response
    {
        // TODO (UC-07): charger, $this->denyAccessUnlessGranted(DeclarationVoter::VIEW, $declaration),
        // générer le PDF (dépendance à ajouter, ex: knp-snappy ou dompdf/dompdf),
        // retourner une Response avec Content-Type: application/pdf.
        throw new \RuntimeException('TODO: implement DeclarationController::exportPdf()');
    }
}
