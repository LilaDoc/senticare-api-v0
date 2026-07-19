<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SecurityController extends AbstractController
{
    /**
     * Appelée par le front juste après l'authentification JWT pour récupérer
     * l'identité et les rôles de l'utilisateur connecté.
     *
     * C'est le FRONT qui décide où rediriger, en lisant les rôles renvoyés ici.
     */
    #[Route(path: '/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'nom' =>$user->getNom(),
            'prenom'=>$user->getPrenom()
            // ajoute ici les champs utiles au front : nom, prénom, service, etc.
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
