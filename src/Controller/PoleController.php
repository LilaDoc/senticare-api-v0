<?php

namespace App\Controller;

use App\Entity\Pole;
use App\Repository\PoleRepository;
use App\Service\PoleManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;

/**
 * Gestion des pôles médicaux (CDC §1.1, UC-12) — réservé à ROLE_ADMIN.
 * Rappel CDC §3 : l'admin n'a par ailleurs aucun accès aux déclarations.
 *
 * Note : `doctrine.orm.controller_resolver.auto_mapping` est désactivé
 * (config/packages/doctrine.yaml) — les entités ne sont donc PAS résolues
 * automatiquement depuis {id}, on les charge explicitement via le repository.
 */
#[Route('/api/admin/poles')]
#[IsGranted('ROLE_ADMIN')]
class PoleController extends AbstractController
{
    public function __construct(
        private readonly PoleManager $poleManager,
        private readonly PoleRepository $poleRepository,
    ) {
    }

    #[Route('', name: 'admin_poles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $poles = $this->poleRepository->findAll();

        return $this->json(array_map(
            fn (Pole $pole): array => [
                'id' => (string) $pole->getId(),
                'nom' => $pole->getNom(),
            ],
            $poles
        ));
    }

    #[Route('/{id}', name: 'admin_poles_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $pole = $this->poleRepository->find($id) ?? throw $this->createNotFoundException();

        return $this->json([
            'id' => (string) $pole->getId(),
            'nom' => $pole->getNom(),
        ]);
    }

    #[Route('', name: 'admin_poles_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $nom = $data['nom'] ?? null;

        if (!$nom) {
            return $this->json(['error' => 'Le champ "nom" est requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $pole = $this->poleManager->create($nom, $admin);

        return $this->json([
            'id' => (string) $pole->getId(),
            'nom' => $pole->getNom(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_poles_update', methods: ['PATCH'])]
    public function update(string $id, Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        $pole = $this->poleRepository->find($id) ?? throw $this->createNotFoundException();

        $data = json_decode($request->getContent(), true);
        $nom = $data['nom'] ?? null;

        if (!$nom) {
            return $this->json(['error' => 'Le champ "nom" est requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $pole = $this->poleManager->update($pole, $nom, $admin);

        return $this->json([
            'id' => (string) $pole->getId(),
            'nom' => $pole->getNom(),
        ]);
    }

    #[Route('/{id}/deactivate', name: 'admin_poles_deactivate', methods: ['PATCH'])]
    public function deactivate(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        $pole = $this->poleRepository->find($id) ?? throw $this->createNotFoundException();

        $this->poleManager->deactivate($pole, $admin);

        return $this->json([
            'id' => (string) $pole->getId(),
            'nom' => $pole->getNom(),
            'isActive' => $pole->isActive(),
        ]);
    }
}
