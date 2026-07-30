<?php

namespace App\Controller;

use App\Entity\LogEntry;
use App\Enum\LogTypeEnum;
use App\Repository\UserRepository;
use App\Service\LogManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Consultation du journal d'audit (CDC §6.3, UC-11) — réservé à ROLE_ADMIN
 * exclusivement, comme le reste de l'administration technique (pôles,
 * comptes chefs de pôle). Aucun accès aux déclarations d'EI ici non plus.
 */
#[Route('/api/admin/logs')]
#[IsGranted('ROLE_ADMIN')]
class LogController extends AbstractController
{
    public function __construct(
        private readonly LogManager $logManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'logs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // GET n'a pas de body : filtres depuis la query string, même pattern
        // que DeclarationController::list() (tryFrom() + 400 propre, jamais
        // from() qui lèverait un ValueError non capturé).
        $query = $request->query;
        $filters = [];

        if ($typeValue = $query->get('type')) {
            $type = LogTypeEnum::tryFrom($typeValue);
            if (!$type) {
                return $this->json(['error' => 'Type de log invalide.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $filters['type'] = $type;
        }

        if ($userId = $query->get('user')) {
            $user = $this->userRepository->find($userId) ?? throw $this->createNotFoundException();
            $filters['user'] = $user;
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

        $entries = $this->logManager->search($filters);

        return $this->json(array_map($this->serialize(...), $entries));
    }

    private function serialize(LogEntry $entry): array
    {
        return [
            'id' => (string) $entry->getId(),
            'type' => [
                'value' => $entry->getType()->value,
                'label' => $entry->getType()->label(),
            ],
            'user' => $entry->getUser() ? [
                'id' => (string) $entry->getUser()->getId(),
                'email' => $entry->getUser()->getUserIdentifier(),
            ] : null,
            'message' => $entry->getMessage(),
            'createdAt' => $entry->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
