<?php

namespace App\Service;

use App\Entity\LogEntry;
use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Journal d'audit (CDC §6.3, UC-11) — connexions, soumissions, actions
 * sensibles. Consulté par ROLE_ADMIN via LogController.
 */
class LogManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LogEntryRepository $logEntryRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Enregistre un événement. Appelée depuis les Managers concernés
     * (UserManager::create()/deactivate()/reactivate(), PoleManager,
     * ServiceManager, DeclarationManager::submit()) et depuis un
     * EventSubscriber sur les événements de connexion Symfony Security.
     *
     * Ne doit jamais faire échouer l'action qu'elle trace : une panne du
     * journal d'audit ne doit pas empêcher une désactivation de compte ou
     * une soumission de déclaration (même esprit que
     * NotificationManager::notifySubmission() — CDC §6.3).
     */
    public function log(LogTypeEnum $type, ?User $user, string $message): void
    {
        $log = new LogEntry();
        $log->setType($type);
        $log->setUser($user);
        $log->setMessage($message);

        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Échec d\'écriture dans le journal d\'audit', [
                'type' => $type->value,
                'userId' => $user?->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Liste filtrée pour l'écran admin (UC-11).
     *
     * @param array{type?: LogTypeEnum, user?: User, dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable} $filters
     *
     * @return LogEntry[]
     */
    public function search(array $filters): array
    {
        return $this->logEntryRepository->search($filters);
    }
}

