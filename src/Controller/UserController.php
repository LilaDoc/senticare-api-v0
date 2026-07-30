<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\RoleEnum;
use App\Repository\ServiceRepository;
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
        private readonly ServiceRepository $serviceRepository,
    ) {
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $currentUser): JsonResponse
    {
        if ($this->isGranted('ROLE_CHEF_POLE')) {
            // Convention (docs/ROADMAP.md) : le pôle d'un chef de pôle se déduit
            // de n'importe lequel de ses propres services (tous dans le même pôle).
            $firstService = $currentUser->getServices()->first() ?: null;
            $pole = $firstService?->getPole();
            $users = $pole ? $this->userManager->findByPole($pole) : [];
        } elseif ($this->isGranted('ROLE_ADMIN')) {
            $users = $this->userManager->findChefsPole();
        } else {
            throw $this->createAccessDeniedException();
        }

        return $this->json(array_map(
            fn (User $user): array => [
                'id' => (string) $user->getId(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getUserIdentifier(),
                'isActive' => $user->isActive(),
            ],
            $users
        ));
    }

    #[Route('', name: 'users_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $currentUser): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserVoter::CREATE);

        $data = json_decode($request->getContent(), true);
        $nom = $data['nom'] ?? null;
        $prenom = $data['prenom'] ?? null;
        $email = $data['email'] ?? null;
        $roleValue = $data['role'] ?? null;
        $serviceIds = $data['serviceIds'] ?? [];

        if (!$nom || !$prenom || !$email || !$roleValue) {
            return $this->json(['error' => 'Les champs "nom", "prenom", "email" et "role" sont requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $role = RoleEnum::tryFrom($roleValue);

        if (!$role) {
            return $this->json(['error' => 'Rôle invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // CDC §3 : un chef de pôle ne crée que des soignants/cadres, un admin
        // que des chefs de pôle — vérification indépendante du Voter, qui ne
        // connaît pas encore le rôle cible demandé dans le payload.
        if ($this->isGranted('ROLE_ADMIN')) {
            if (RoleEnum::ChefPole !== $role) {
                return $this->json(['error' => 'Un administrateur ne peut créer que des comptes chef de pôle.'], JsonResponse::HTTP_FORBIDDEN);
            }
        } elseif (!in_array($role, [RoleEnum::Soignant, RoleEnum::Cadre], true)) {
            return $this->json(['error' => 'Un chef de pôle ne peut créer que des comptes soignant ou cadre.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $services = array_map(
            fn (string $serviceId) => $this->serviceRepository->find($serviceId) ?? throw $this->createNotFoundException(),
            $serviceIds
        );

        $user = $this->userManager->create($email, $nom, $prenom, $role, $currentUser, $services);

        return $this->json([
            'id' => (string) $user->getId(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getUserIdentifier(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/me/password', name: 'users_change_password', methods: ['PATCH'])]
    public function changePassword(Request $request, #[CurrentUser] User $currentUser): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        if (!$currentPassword || !$newPassword) {
            return $this->json(['error' => 'Les champs "currentPassword" et "newPassword" sont requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $this->userManager->changePassword($currentUser, $currentPassword, $newPassword);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Mot de passe modifié avec succès.']);
    }

    #[Route('/{id}', name: 'users_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(UserVoter::EDIT, $user);

        $data = json_decode($request->getContent(), true);
        $nom = $data['nom'] ?? $user->getNom();
        $prenom = $data['prenom'] ?? $user->getPrenom();
        $serviceIds = $data['serviceIds'] ?? null;

        // Pas de champ fourni -> on garde les services actuels inchangés.
        $services = null === $serviceIds
            ? $user->getServices()->toArray()
            : array_map(
                fn (string $serviceId) => $this->serviceRepository->find($serviceId) ?? throw $this->createNotFoundException(),
                $serviceIds
            );

        $this->userManager->update($user, $nom, $prenom, $services);

        return $this->json([
            'id' => (string) $user->getId(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'email' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/{id}/deactivate', name: 'users_deactivate', methods: ['PATCH'])]
    public function deactivate(string $id, #[CurrentUser] User $currentUser): JsonResponse
    {
        $user = $this->userRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(UserVoter::DEACTIVATE, $user);

        $this->userManager->deactivate($user, $currentUser);

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getUserIdentifier(),
            'isActive' => $user->isActive(),
        ]);
    }

    #[Route('/{id}/reactivate', name: 'users_reactivate', methods: ['PATCH'])]
    public function reactivate(string $id, #[CurrentUser] User $currentUser): JsonResponse
    {
        $user = $this->userRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(UserVoter::REACTIVATE, $user);

        $this->userManager->reactivate($user, $currentUser);

        return $this->json([
            'id' => (string) $user->getId(),
            'email' => $user->getUserIdentifier(),
            'isActive' => $user->isActive(),
        ]);
    }
}
