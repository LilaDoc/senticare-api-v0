<?php

namespace App\Service;

use App\Entity\Pole;
use App\Entity\User;
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
    ) {
    }

    public function create(string $nom, User $createdBy): Pole
    {
        $pole = new Pole();
        $pole->setNom($nom);
        $pole->setCreatedBy($createdBy);

        $this->entityManager->persist($pole);
        $this->entityManager->flush();

        return $pole;
    }

    public function update(Pole $pole, string $nom): Pole
    {
        $pole->setNom($nom);

        $this->entityManager->flush();

        return $pole;
    }

    public function deactivate(Pole $pole): void
    {
        $pole->setIsActive(false);

        $this->entityManager->flush();
    }
}
