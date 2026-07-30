<?php

namespace App\Service;

use App\Entity\Pole;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Enum\LogTypeEnum;
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
        private readonly LogManager $logManager,
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

        $this->logManager->log(LogTypeEnum::ServiceCreated, $createdBy, sprintf('Service %s créé par %s', $service->getNom(), $createdBy->getEmail()));

        return $service;
    }

    public function update(ServiceEntity $service, string $nom, User $actor, ?Pole $pole = null): ServiceEntity
    {
        $service->setNom($nom);

        if (null !== $pole) {
            $service->setPole($pole);
        }

        $this->entityManager->flush();

        $this->logManager->log(LogTypeEnum::ServiceUpdated, $actor, sprintf('Service %s modifié par %s', $service->getNom(), $actor->getEmail()));

        return $service;
    }

    public function deactivate(ServiceEntity $service, User $actor): void
    {
        $service->setIsActive(false);

        $this->entityManager->flush();

        $this->logManager->log(LogTypeEnum::ServiceDeactivated, $actor, sprintf('Service %s désactivé par %s', $service->getNom(), $actor->getEmail()));
    }
}
