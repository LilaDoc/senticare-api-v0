<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Pole;
use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC UC-12 : gestion des pôles réservée à ROLE_ADMIN.
 */
class PoleControllerTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/poles');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testChefPoleCannotAccessAdminPolesEndpoint(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $chefPole = $this->createUser('chefpole@test.fr', RoleEnum::ChefPole, [$service]);
        $this->authenticateAs($chefPole);

        $this->client->request('GET', '/api/admin/poles');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCanReachPolesEndpoint(): void
    {
        $admin = $this->createUser('admin@test.fr', RoleEnum::Admin);
        $this->authenticateAs($admin);

        $this->client->request('GET', '/api/admin/poles');

        // TODO: une fois PoleController::list() implémenté, attendre 200 + tableau JSON.
        self::assertNotSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCanCreatePole(): void
    {
        $admin = $this->createUser('admin2@test.fr', RoleEnum::Admin);
        $this->authenticateAs($admin);

        $this->client->request(
            'POST',
            '/api/admin/poles',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['nom' => 'Pôle Maternité'])
        );

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $pole = $this->entityManager->getRepository(Pole::class)->find($data['id']);

        self::assertSame('Pôle Maternité', $pole->getNom());
        self::assertSame((string) $admin->getId(), (string) $pole->getCreatedBy()->getId());
    }
}
