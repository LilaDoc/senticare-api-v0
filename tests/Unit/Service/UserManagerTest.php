<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Service\LogManager;
use App\Service\NotificationManager;
use App\Service\UserManager;
use App\Tests\Unit\Fakes\InMemoryUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test unitaire (cf. 01_tests-2.md, niveau 1). Contrairement à
 * PoleManagerTest/ServiceManagerTest, UserManager utilise vraiment son
 * Repository (vérifier l'unicité d'un email) — un FAKE en mémoire
 * (InMemoryUserRepository) a donc un vrai intérêt ici, comme
 * CommandeRepositoryEnMemoire dans le cours.
 */
class UserManagerTest extends TestCase
{
    private function createChefPole(): User
    {
        $chefPole = new User();
        $chefPole->setEmail('chef@test.fr');
        $chefPole->setNom('Nom');
        $chefPole->setPrenom('Prenom');
        $chefPole->setRole(RoleEnum::ChefPole);
        $chefPole->setPassword('hash-non-pertinent-pour-ce-test');

        return $chefPole;
    }

    private function createManager(
        InMemoryUserRepository $userRepository,
        ?EntityManagerInterface $entityManager = null,
        ?NotificationManager $notificationManager = null,
        ?LogManager $logManager = null,
    ): UserManager {
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('mot-de-passe-hashe');

        return new UserManager(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $userRepository,
            $passwordHasher,
            $notificationManager ?? $this->createStub(NotificationManager::class),
            $logManager ?? $this->createStub(LogManager::class),
        );
    }

    public function testCreateThrowsIfEmailAlreadyExists(): void
    {
        $userRepository = new InMemoryUserRepository();
        $existant = new User();
        $existant->setEmail('soignant@test.fr');
        $userRepository->add($existant);

        $manager = $this->createManager($userRepository);

        $this->expectException(\RuntimeException::class);

        $manager->create('soignant@test.fr', 'Dupont', 'Jean', RoleEnum::Soignant, $this->createChefPole());
    }

    public function testCreatePersistsNotifiesAndLogsWhenEmailIsFree(): void
    {
        $chefPole = $this->createChefPole();
        $userRepository = new InMemoryUserRepository();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $entityManager->expects($this->once())->method('flush');

        $notificationManager = $this->createMock(NotificationManager::class);
        $notificationManager->expects($this->once())
            ->method('notifyAccountCreated')
            ->with($this->isInstanceOf(User::class), $this->isString());

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::UserCreated, $chefPole, $this->stringContains('soignant@test.fr'));

        $manager = $this->createManager($userRepository, $entityManager, $notificationManager, $logManager);

        $user = $manager->create('soignant@test.fr', 'Dupont', 'Jean', RoleEnum::Soignant, $chefPole);

        self::assertSame('soignant@test.fr', $user->getEmail());
        self::assertContains(RoleEnum::Soignant->value, $user->getRoles());
        self::assertSame($chefPole, $user->getCreatedBy());
    }

    public function testDeactivateSetsInactiveAndLogs(): void
    {
        $actor = $this->createChefPole();
        $user = new User();
        $user->setEmail('soignant@test.fr');

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::UserDeactivated, $actor, $this->stringContains('soignant@test.fr'));

        $manager = $this->createManager(new InMemoryUserRepository(), logManager: $logManager);

        $manager->deactivate($user, $actor);

        self::assertFalse($user->isActive());
    }

    public function testReactivateSetsActiveAndLogs(): void
    {
        $actor = $this->createChefPole();
        $user = new User();
        $user->setEmail('soignant@test.fr');
        $user->setIsActive(false);

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::UserReactivated, $actor, $this->stringContains('soignant@test.fr'));

        $manager = $this->createManager(new InMemoryUserRepository(), logManager: $logManager);

        $manager->reactivate($user, $actor);

        self::assertTrue($user->isActive());
    }

    public function testChangePasswordThrowsOnWrongCurrentPassword(): void
    {
        $user = new User();
        $user->setPassword('ancien-hash');

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('isPasswordValid')->willReturn(false);

        $manager = new UserManager(
            $this->createStub(EntityManagerInterface::class),
            new InMemoryUserRepository(),
            $passwordHasher,
            $this->createStub(NotificationManager::class),
            $this->createStub(LogManager::class),
        );

        $this->expectException(\RuntimeException::class);

        $manager->changePassword($user, 'mauvais-mot-de-passe', 'nouveau-mot-de-passe');
    }

    public function testChangePasswordUpdatesHashOnSuccess(): void
    {
        $user = new User();
        $user->setPassword('ancien-hash');

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('isPasswordValid')->willReturn(true);
        $passwordHasher->method('hashPassword')->willReturn('nouveau-hash');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $manager = new UserManager(
            $entityManager,
            new InMemoryUserRepository(),
            $passwordHasher,
            $this->createStub(NotificationManager::class),
            $this->createStub(LogManager::class),
        );

        $manager->changePassword($user, 'ancien-mot-de-passe', 'nouveau-mot-de-passe');

        self::assertSame('nouveau-hash', $user->getPassword());
    }
}
