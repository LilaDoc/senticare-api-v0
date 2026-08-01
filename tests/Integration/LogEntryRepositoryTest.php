<?php

namespace App\Tests\Integration;

use App\Entity\LogEntry;
use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Repository\LogEntryRepository;

/**
 * Test d'intégration (cf. 01_tests-2.md, niveau 2) : LogEntryRepository
 * dialogue-t-il correctement avec la vraie base Postgres.
 */
class LogEntryRepositoryTest extends RepositoryIntegrationTestCase
{
    private LogEntryRepository $logEntryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logEntryRepository = static::getContainer()->get(LogEntryRepository::class);
    }

    private function persistUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setNom('Nom');
        $user->setPrenom('Prenom');
        $user->setRole(RoleEnum::Admin);
        $user->setPassword('hash-non-pertinent-pour-ce-test');

        $this->entityManager->persist($user);

        return $user;
    }

    private function persistLogEntry(LogTypeEnum $type, ?User $user, string $message): LogEntry
    {
        $entry = new LogEntry();
        $entry->setType($type);
        $entry->setUser($user);
        $entry->setMessage($message);

        $this->entityManager->persist($entry);

        return $entry;
    }

    public function testSearchFiltersByType(): void
    {
        $this->persistLogEntry(LogTypeEnum::LoginSuccess, null, 'connexion');
        $this->persistLogEntry(LogTypeEnum::UserCreated, null, 'création compte');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $resultats = $this->logEntryRepository->search(['type' => LogTypeEnum::UserCreated]);

        self::assertCount(1, $resultats);
        self::assertSame(LogTypeEnum::UserCreated, $resultats[0]->getType());
    }

    public function testSearchFiltersByUser(): void
    {
        $admin = $this->persistUser('admin@test.fr');
        $autreAdmin = $this->persistUser('autre-admin@test.fr');
        $this->persistLogEntry(LogTypeEnum::PoleCreated, $admin, 'pôle créé par admin');
        $this->persistLogEntry(LogTypeEnum::PoleCreated, $autreAdmin, 'pôle créé par autre-admin');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $admin = $this->entityManager->getRepository(User::class)->find($admin->getId());
        $resultats = $this->logEntryRepository->search(['user' => $admin]);

        self::assertCount(1, $resultats);
        self::assertSame('pôle créé par admin', $resultats[0]->getMessage());
    }

    public function testSearchFiltersByDateRange(): void
    {
        $this->persistLogEntry(LogTypeEnum::LoginSuccess, null, 'connexion récente');
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Borne dans le passé : l'entrée (créée "maintenant") doit apparaître.
        $avecBorneDansLePasse = $this->logEntryRepository->search(['dateFrom' => new \DateTimeImmutable('-1 hour')]);
        self::assertNotEmpty($avecBorneDansLePasse);

        // Borne dans le futur : l'entrée ne doit jamais apparaître.
        $avecBorneDansLeFutur = $this->logEntryRepository->search(['dateFrom' => new \DateTimeImmutable('+1 hour')]);
        self::assertSame([], $avecBorneDansLeFutur);
    }

    public function testSearchWithoutFiltersReturnsEverything(): void
    {
        $this->persistLogEntry(LogTypeEnum::LoginSuccess, null, 'un');
        $this->persistLogEntry(LogTypeEnum::LoginFailure, null, 'deux');
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertCount(2, $this->logEntryRepository->search([]));
    }
}
