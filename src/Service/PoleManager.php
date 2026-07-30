<?php

namespace App\Service;

use App\Entity\Pole;
use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Repository\PoleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion des pôles médicaux (CDC §1.1, UC-12) — réservé à ROLE_ADMIN.
 */
class PoleManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PoleRepository $poleRepository,
        private readonly LogManager $logManager,
    ) {
    }

    public function create(string $nom, User $createdBy): Pole
    {
        $pole = new Pole();
        $pole->setNom($nom);
        $pole->setCreatedBy($createdBy);

        $this->entityManager->persist($pole);
        $this->entityManager->flush();

        $this->logManager->log(LogTypeEnum::PoleCreated, $createdBy, sprintf('Pôle %s créé par %s', $pole->getNom(), $createdBy->getEmail()));

        return $pole;
    }

    public function update(Pole $pole, string $nom, User $actor): Pole
    {
        $pole->setNom($nom);

        $this->entityManager->flush();

        $this->logManager->log(LogTypeEnum::PoleUpdated, $actor, sprintf('Pôle %s modifié par %s', $pole->getNom(), $actor->getEmail()));

        return $pole;
    }

    public function deactivate(Pole $pole, User $actor): void
    {
        $pole->setIsActive(false);

        $this->entityManager->flush();

        $this->logManager->log(LogTypeEnum::PoleDeactivated, $actor, sprintf('Pôle %s désactivé par %s', $pole->getNom(), $actor->getEmail()));
    }
}
