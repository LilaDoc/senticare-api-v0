<?php

namespace App\Tests\Unit\Service;

use App\Entity\LogEntry;
use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Repository\LogEntryRepository;
use App\Service\LogManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test unitaire (cf. 01_tests-2.md, niveau 1).
 */
class LogManagerTest extends TestCase
{
    public function testLogPersistsEntryWithCorrectFields(): void
    {
        $user = new User();
        $user->setEmail('admin@test.fr');
        $user->setPassword('hash');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (LogEntry $entry) use ($user): bool {
                return LogTypeEnum::UserCreated === $entry->getType()
                    && $user === $entry->getUser()
                    && 'un message' === $entry->getMessage();
            }));
        $entityManager->expects($this->once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $manager = new LogManager($entityManager, $this->createStub(LogEntryRepository::class), $logger);

        $manager->log(LogTypeEnum::UserCreated, $user, 'un message');
    }

    public function testLogAcceptsNullUser(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn (LogEntry $entry): bool => null === $entry->getUser()));

        $manager = new LogManager($entityManager, $this->createStub(LogEntryRepository::class), $this->createStub(LoggerInterface::class));

        $manager->log(LogTypeEnum::LoginFailure, null, 'Échec de connexion pour : inconnu@test.fr');
    }

    public function testLogCatchesExceptionAndLogsErrorWithoutThrowing(): void
    {
        $user = new User();
        $user->setEmail('admin@test.fr');
        $user->setPassword('hash');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException(new \RuntimeException('Panne de connexion à la base'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $manager = new LogManager($entityManager, $this->createStub(LogEntryRepository::class), $logger);

        // Ne doit jamais laisser l'exception remonter (CDC §6.3 : une panne
        // du journal d'audit ne doit pas bloquer l'action qu'elle trace).
        $manager->log(LogTypeEnum::UserCreated, $user, 'un message');
    }

    public function testSearchDelegatesToRepository(): void
    {
        $filters = ['type' => LogTypeEnum::UserCreated];
        $resultatAttendu = [new LogEntry()];

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->expects($this->once())->method('search')->with($filters)->willReturn($resultatAttendu);

        $manager = new LogManager($this->createStub(EntityManagerInterface::class), $repository, $this->createStub(LoggerInterface::class));

        self::assertSame($resultatAttendu, $manager->search($filters));
    }
}
