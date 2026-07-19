<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\UserVoter;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Gestion des comptes utilisateurs (CDC UC-10, UC-12, US-1.2, US-1.3).
 *
 * Pas de #[IsGranted('ROLE_...')] au niveau classe/méthode ici : le rôle minimal
 * ("chef de pôle OU admin") n'est pas exprimable proprement avec un seul rôle
 * (ROLE_ADMIN est orthogonal à la hiérarchie, CDC §3). Le contrôle est donc
 * intégralement délégué à UserVoter via denyAccessUnlessGranted().
 */
#[Route('/api/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $currentUser): JsonResponse
    {
        // TODO: chef de pôle -> UserManager::findByPole() ; admin -> UserManager::findChefsPole().
        // Vérifier via $this->isGranted('ROLE_CHEF_POLE') / ('ROLE_ADMIN') lequel des deux appliquer.
        throw new \RuntimeException('TODO: implement UserController::list()');
    }

    #[Route('', name: 'users_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $currentUser): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::CREATE);

        // TODO: décoder payload { email, nom, prenom, role, services[] } ; si $currentUser a
        // ROLE_CHEF_POLE, le role cible doit être Soignant/Cadre ; si ROLE_ADMIN, ChefPole
        // uniquement (CDC §3) — à vérifier explicitement, indépendamment du Voter.
        // Déléguer à UserManager::create(), retourner 201.
        throw new \RuntimeException('TODO: implement UserController::create()');
    }

    #[Route('/{id}', name: 'users_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        // TODO: charger $user = $this->userRepository->find($id) ?? 404,
        // $this->denyAccessUnlessGranted(UserVoter::EDIT, $user),
        // décoder payload { nom, prenom, serviceIds[] }, résoudre les services,
        // déléguer à $this->userManager->update().
        throw new \RuntimeException('TODO: implement UserController::update()');
    }

    #[Route('/{id}/deactivate', name: 'users_deactivate', methods: ['PATCH'])]
    public function deactivate(string $id): JsonResponse
    {
        // TODO: charger $user = $this->userRepository->find($id) ?? 404,
        // $this->denyAccessUnlessGranted(UserVoter::DEACTIVATE, $user),
        // $this->userManager->deactivate($user).
        throw new \RuntimeException('TODO: implement UserController::deactivate()');
    }

    #[Route('/{id}/reactivate', name: 'users_reactivate', methods: ['PATCH'])]
    public function reactivate(string $id): JsonResponse
    {
        // TODO: charger $user = $this->userRepository->find($id) ?? 404,
        // $this->denyAccessUnlessGranted(UserVoter::REACTIVATE, $user),
        // $this->userManager->reactivate($user).
        throw new \RuntimeException('TODO: implement UserController::reactivate()');
    }
}
