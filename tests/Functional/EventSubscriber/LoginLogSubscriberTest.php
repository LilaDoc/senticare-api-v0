<?php

namespace App\Tests\Functional\EventSubscriber;

use App\Entity\LogEntry;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC §6.3 : les connexions (réussies et échouées) doivent être tracées
 * dans le journal d'audit — câblage via LoginLogSubscriber (Symfony Security
 * events), pas d'appel direct depuis un contrôleur.
 */
class LoginLogSubscriberTest extends ApiTestCase
{
    public function testSuccessfulLoginWritesALogEntry(): void
    {
        // Mot de passe fixé par ApiTestCase::createUser() ('Test1234!').
        $user = $this->createUser('soignant@test.fr', RoleEnum::Soignant);

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'soignant@test.fr', 'password' => 'Test1234!'])
        );

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $entries = $this->entityManager->getRepository(LogEntry::class)->findBy(['type' => LogTypeEnum::LoginSuccess]);

        self::assertCount(1, $entries);
        self::assertSame($user->getId(), $entries[0]->getUser()?->getId());
        self::assertStringContainsString('soignant@test.fr', $entries[0]->getMessage());
    }

    public function testFailedLoginWritesALogEntryWithoutUser(): void
    {
        $this->createUser('soignant@test.fr', RoleEnum::Soignant);

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'soignant@test.fr', 'password' => 'MauvaisMotDePasse!'])
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        $entries = $this->entityManager->getRepository(LogEntry::class)->findBy(['type' => LogTypeEnum::LoginFailure]);

        self::assertCount(1, $entries);
        self::assertNull($entries[0]->getUser());
        self::assertStringContainsString('soignant@test.fr', $entries[0]->getMessage());
    }
}
