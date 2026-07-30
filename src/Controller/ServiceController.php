<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\User;
use App\Repository\PoleRepository;
use App\Repository\ServiceRepository;
use App\Security\Voter\ServiceVoter;
use App\Service\ServiceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Gestion des services médicaux (CDC UC-13, US-1.2/US-1.3).
 *
 * Partagé entre admin (tous pôles) et chef de pôle (son pôle uniquement) — pas
 * de #[IsGranted('ROLE_...')] au niveau classe pour la même raison que
 * UserController : ROLE_ADMIN est orthogonal à la hiérarchie (CDC §3), donc
 * "chef de pôle OU admin" n'est pas exprimable avec un seul rôle. Le contrôle
 * est intégralement délégué à ServiceVoter.
 *
 * Note : `doctrine.orm.controller_resolver.auto_mapping` est désactivé
 * (config/packages/doctrine.yaml) — les entités ne sont donc PAS résolues
 * automatiquement depuis {id}, on les charge explicitement via le repository.
 */
#[Route('/api/services')]
class ServiceController extends AbstractController
{
    public function __construct(
        private readonly ServiceManager $serviceManager,
        private readonly ServiceRepository $serviceRepository,
        private readonly PoleRepository $poleRepository,
    ) {
    }

    #[Route('', name: 'services_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $currentUser): JsonResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $services = $this->serviceRepository->findAll();
        } elseif ($this->isGranted('ROLE_CHEF_POLE')) {
            // Convention (docs/ROADMAP.md) : un chef de pôle est rattaché à
            // TOUS les services de son pôle -> sa propre collection de
            // services EST la liste des services de son pôle.
            $services = $currentUser->getServices()->toArray();
        } else {
            throw $this->createAccessDeniedException();
        }

        return $this->json(array_map(
            fn (Service $service): array => [
                'id' => (string) $service->getId(),
                'nom' => $service->getNom(),
                'isActive' => $service->isActive(),
            ],
            $services
        ));
    }

    #[Route('/{id}', name: 'services_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $service = $this->serviceRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(ServiceVoter::VIEW, $service);

        return $this->json([
            'id' => (string) $service->getId(),
            'nom' => $service->getNom(),
            'isActive' => $service->isActive(),
        ]);
    }

    #[Route('', name: 'services_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $currentUser): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $nom = $data['nom'] ?? null;
        $poleId = $data['poleId'] ?? null;

        if (!$nom || !$poleId) {
            return $this->json(['error' => 'Les champs "nom" et "poleId" sont requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $pole = $this->poleRepository->find($poleId) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(ServiceVoter::CREATE, $pole);

        $service = $this->serviceManager->create($nom, $pole, $currentUser);

        return $this->json([
            'id' => (string) $service->getId(),
            'nom' => $service->getNom(),
            'isActive' => $service->isActive(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'services_update', methods: ['PATCH'])]
    public function update(string $id, Request $request, #[CurrentUser] User $currentUser): JsonResponse
    {
        $service = $this->serviceRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(ServiceVoter::EDIT, $service);

        $data = json_decode($request->getContent(), true);
        $nom = $data['nom'] ?? null;
        $poleId = $data['poleId'] ?? null;

        if (!$nom) {
            return $this->json(['error' => 'Le champ "nom" est requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $pole = null;
        if (null !== $poleId) {
            $pole = $this->poleRepository->find($poleId) ?? throw $this->createNotFoundException();
        }

        $this->serviceManager->update($service, $nom, $currentUser, $pole);

        return $this->json([
            'id' => (string) $service->getId(),
            'nom' => $service->getNom(),
            'isActive' => $service->isActive(),
        ]);
    }

    #[Route('/{id}/deactivate', name: 'services_deactivate', methods: ['PATCH'])]
    public function deactivate(string $id, #[CurrentUser] User $currentUser): JsonResponse
    {
        $service = $this->serviceRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(ServiceVoter::DEACTIVATE, $service);

        $this->serviceManager->deactivate($service, $currentUser);

        return $this->json([
            'id' => (string) $service->getId(),
            'nom' => $service->getNom(),
            'isActive' => $service->isActive(),
        ]);
    }

}
