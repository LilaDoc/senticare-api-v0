<?php

namespace App\Tests\Unit\Service;

use App\Entity\Pole;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Repository\ServiceRepository;
use App\Service\LogManager;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire (cf. 01_tests-2.md, niveau 1). Même constat que PoleManager :
 * ServiceRepository n'est jamais utilisé par ServiceManager (stub muet),
 * seuls EntityManagerInterface et LogManager sont doublés en Mock.
 */
class ServiceManagerTest extends TestCase
{
    private function createAdmin(): User
    {
        $admin = new User();
        $admin->setEmail('admin@test.fr');
        $admin->setNom('Nom');
        $admin->setPrenom('Prenom');
        $admin->setRole(RoleEnum::Admin);
        $admin->setPassword('hash-non-pertinent-pour-ce-test');

        return $admin;
    }

    public function testCreatePersistsServiceAndLogsCreation(): void
    {
        $admin = $this->createAdmin();
        $pole = new Pole();
        $pole->setNom('Chirurgie');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(ServiceEntity::class));
        $entityManager->expects($this->once())->method('flush');

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::ServiceCreated, $admin, $this->stringContains('Bloc opératoire'));

        $manager = new ServiceManager($entityManager, $this->createStub(ServiceRepository::class), $logManager);

        $service = $manager->create('Bloc opératoire', $pole, $admin);

        self::assertSame('Bloc opératoire', $service->getNom());
        self::assertSame($pole, $service->getPole());
        self::assertSame($admin, $service->getCreatedBy());
    }

    public function testUpdateWithoutPoleKeepsExistingPole(): void
    {
        $actor = $this->createAdmin();
        $originalPole = new Pole();
        $originalPole->setNom('Chirurgie');

        $service = new ServiceEntity();
        $service->setNom('Ancien nom');
        $service->setPole($originalPole);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::ServiceUpdated, $actor, $this->stringContains('Nouveau nom'));

        $manager = new ServiceManager($entityManager, $this->createStub(ServiceRepository::class), $logManager);

        $updated = $manager->update($service, 'Nouveau nom', $actor);

        self::assertSame('Nouveau nom', $updated->getNom());
        self::assertSame($originalPole, $updated->getPole());
    }

    public function testUpdateWithPoleReassignsService(): void
    {
        $actor = $this->createAdmin();
        $originalPole = new Pole();
        $originalPole->setNom('Chirurgie');
        $nouveauPole = new Pole();
        $nouveauPole->setNom('Oncologie');

        $service = new ServiceEntity();
        $service->setNom('Hôpital de jour');
        $service->setPole($originalPole);

        $manager = new ServiceManager(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ServiceRepository::class),
            $this->createStub(LogManager::class),
        );

        $updated = $manager->update($service, 'Hôpital de jour', $actor, $nouveauPole);

        self::assertSame($nouveauPole, $updated->getPole());
    }

    public function testDeactivateSetsInactiveAndLogsDeactivation(): void
    {
        $actor = $this->createAdmin();
        $service = new ServiceEntity();
        $service->setNom('Bloc opératoire');

        self::assertTrue($service->isActive());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::ServiceDeactivated, $actor, $this->stringContains('Bloc opératoire'));

        $manager = new ServiceManager($entityManager, $this->createStub(ServiceRepository::class), $logManager);

        $manager->deactivate($service, $actor);

        self::assertFalse($service->isActive());
    }
}
