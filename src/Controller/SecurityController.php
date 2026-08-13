<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SecurityController extends AbstractController
{
    /**
     * Appelée par le front juste après l'authentification JWT pour récupérer
     * l'identité, les rôles et le périmètre de l'utilisateur connecté.
     *
     * C'est le FRONT qui décide où rediriger, en lisant les rôles renvoyés ici.
     */
    #[Route(path: '/api/me', name: 'api_me', methods: ['GET'])]
    public function me(
        #[CurrentUser] ?User $user,
        RoleHierarchyInterface $roleHierarchy,
    ): JsonResponse {
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Les services du déclarant sont indispensables au wizard : le CDC §4.3
        // impose que le service soit pré-rempli depuis le profil, et
        // POST /api/declarations n'accepte qu'un service auquel il est rattaché.
        // Le pôle est joint ici car un chef de pôle en a besoin (poleId) pour
        // créer un service, or /api/admin/poles lui est interdit (admin only).
        $services = array_map(
            static fn ($service) => [
                'id'   => (string) $service->getId(),
                'nom'  => $service->getNom(),
                'pole' => $service->getPole() ? [
                    'id'  => (string) $service->getPole()->getId(),
                    'nom' => $service->getPole()->getNom(),
                ] : null,
            ],
            $user->getServices()->toArray(),
        );

        return new JsonResponse([
            'id'      => (string) $user->getId(),
            'email'   => $user->getUserIdentifier(),
            'nom'     => $user->getNom(),
            'prenom'  => $user->getPrenom(),
            // Rôle métier unique réellement porté par le compte (CDC §3).
            'role'    => $user->getRole()->value,
            // Rôles effectifs, héritage de security.yaml résolu ici : le front
            // n'a pas à réimplémenter ROLE_CHEF_POLE > ROLE_CADRE > ROLE_SOIGNANT.
            'roles'   => $roleHierarchy->getReachableRoleNames($user->getRoles()),
            'services' => array_values($services),
        ]);
    }

    /**
     * Route de logout technique : jamais exécutée réellement, elle est
     * interceptée en amont par le firewall (clé `logout:` dans security.yaml).
     * Utile seulement si tu veux un point d'entrée nommé côté front/OpenAPI.
     */
    #[Route(path: '/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('Interceptée par le firewall, jamais appelée directement.');
    }
}
