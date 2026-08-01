<?php

namespace App\Tests\Unit\Service;

use App\Entity\Pole;
use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Repository\PoleRepository;
use App\Service\LogManager;
use App\Service\PoleManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire (cf. 01_tests-2.md, niveau 1) : PoleManager n'utilise en
 * réalité jamais PoleRepository (double muet, un simple stub suffit) — ses
 * deux vraies dépendances sont EntityManagerInterface (persist/flush) et
 * LogManager, toutes deux doublées en Mock puisqu'on veut vérifier QU'ELLES
 * ONT ÉTÉ appelées correctement, pas juste contrôler une valeur de retour
 * (voir la distinction Mock/Stub du cours).
 */
class PoleManagerTest extends TestCase
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

    public function testCreatePersistsPoleAndLogsCreation(): void
    {
        // Arrange
        $admin = $this->createAdmin();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Pole::class));
        $entityManager->expects($this->once())->method('flush');

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::PoleCreated, $admin, $this->stringContains('Chirurgie'));

        $manager = new PoleManager($entityManager, $this->createStub(PoleRepository::class), $logManager);

        // Act
        $pole = $manager->create('Chirurgie', $admin);

        // Assert
        self::assertSame('Chirurgie', $pole->getNom());
        self::assertSame($admin, $pole->getCreatedBy());
    }

    public function testUpdateChangesNomAndLogsUpdate(): void
    {
        $actor = $this->createAdmin();
        $pole = new Pole();
        $pole->setNom('Ancien nom');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::PoleUpdated, $actor, $this->stringContains('Nouveau nom'));

        $manager = new PoleManager($entityManager, $this->createStub(PoleRepository::class), $logManager);

        $updated = $manager->update($pole, 'Nouveau nom', $actor);

        self::assertSame('Nouveau nom', $updated->getNom());
    }

    public function testDeactivateSetsInactiveAndLogsDeactivation(): void
    {
        $actor = $this->createAdmin();
        $pole = new Pole();
        $pole->setNom('Chirurgie');

        self::assertTrue($pole->isActive());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::PoleDeactivated, $actor, $this->stringContains('Chirurgie'));

        $manager = new PoleManager($entityManager, $this->createStub(PoleRepository::class), $logManager);

        $manager->deactivate($pole, $actor);

        self::assertFalse($pole->isActive());
    }
}
