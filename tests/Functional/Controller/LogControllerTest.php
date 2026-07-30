<?php

namespace App\Tests\Functional\Controller;

use App\Entity\LogEntry;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC §6.3, UC-11 : consultation du journal d'audit, réservée à ROLE_ADMIN.
 */
class LogControllerTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/logs');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testChefPoleCannotAccessLogs(): void
    {
        // ROLE_ADMIN exclusivement (CDC §3) — un chef de pôle, même haut dans
        // la hiérarchie métier, n'y a pas accès (ROLE_ADMIN est orthogonal).
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $chefPole = $this->createUser('chef@test.fr', RoleEnum::ChefPole, [$service]);
        $this->authenticateAs($chefPole);

        $this->client->request('GET', '/api/admin/logs');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCanListLogs(): void
    {
        $admin = $this->createUser('admin@test.fr', RoleEnum::Admin);
        $this->authenticateAs($admin);

        $this->client->request('GET', '/api/admin/logs');

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testCreatingAUserWritesALogEntry(): void
    {
        // UserManager::create() est câblé sur LogManager::log() — on vérifie
        // ici le câblage de bout en bout, pas la logique de création elle-même
        // (déjà couverte par UserControllerTest). Vérification directe en base
        // plutôt qu'un second aller-retour HTTP : authenticateAs() ne supporte
        // pas bien d'être appelé deux fois dans un même test sur un firewall
        // JWT stateless (aucun autre test du projet ne le fait).
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $chefPole = $this->createUser('chef@test.fr', RoleEnum::ChefPole, [$service]);
        $this->authenticateAs($chefPole);

        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'email' => 'soignant@test.fr',
                'role' => 'ROLE_SOIGNANT',
                'serviceIds' => [(string) $service->getId()],
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $entries = $this->entityManager->getRepository(LogEntry::class)->findBy(['type' => LogTypeEnum::UserCreated]);

        self::assertCount(1, $entries);
        self::assertStringContainsString('soignant@test.fr', $entries[0]->getMessage());
    }
}
