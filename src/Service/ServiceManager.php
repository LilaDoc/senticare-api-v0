<?php

namespace App\Service;

use App\Entity\Pole;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion des services médicaux (CDC §1.1, UC-12) — réservé à ROLE_ADMIN.
 *
 * Nommé ServiceManager (et non ServiceService) pour éviter la confusion avec
 * l'entité App\Entity\Service.
 */
class ServiceManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ServiceRepository $serviceRepository,
    ) {
    }

    public function create(string $nom, Pole $pole, User $createdBy): ServiceEntity
    {
        $service = new ServiceEntity();
        $service->setNom($nom);
        $service->setCreatedBy($createdBy);
        $service->setPole($pole);

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $service;
    }

    public function update(ServiceEntity $service, string $nom, ?Pole $pole = null): ServiceEntity
    {
        $service->setNom($nom);

        if (null !== $pole) {
            $service->setPole($pole);
        }

        $this->entityManager->flush();

        return $service;
    }

    public function deactivate(ServiceEntity $service): void
    {
        $service->setIsActive(false);

        $this->entityManager->flush();
    }
}
