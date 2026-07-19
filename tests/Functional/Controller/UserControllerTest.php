<?php

namespace App\Tests\Functional\Controller;

use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC UC-10, UC-12, US-1.2, US-1.3 : création/gestion des comptes.
 */
class UserControllerTest extends ApiTestCase
{
    public function testCreateRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/users', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testSoignantCannotCreateAccounts(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $soignant = $this->createUser('soignant@test.fr', RoleEnum::Soignant, [$service]);
        $this->authenticateAs($soignant);

        $this->client->request('POST', '/api/users', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testChefPoleCanCreateSoignantAccount(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $chefPole = $this->createUser('chefpole@test.fr', RoleEnum::ChefPole, [$service]);
        $this->authenticateAs($chefPole);

        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'nouveau.soignant@test.fr',
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'role' => 'ROLE_SOIGNANT',
                'serviceIds' => [(string) $service->getId()],
            ])
        );

        // TODO: une fois UserController::create() + UserManager::create() implémentés,
        // attendre 201 + vérifier qu'un email avec mot de passe provisoire a été envoyé (US-1.2).
        self::markTestIncomplete('UserController::create() not implemented yet.');
    }

    public function testChefPoleCannotCreateChefPoleAccount(): void
    {
        // CDC §3 : seul l'admin crée des comptes chefs de pôle.
        self::markTestIncomplete('UserController::create() role validation not implemented yet.');
    }

    public function testAdminCanCreateChefPoleAccount(): void
    {
        self::markTestIncomplete('UserController::create() not implemented yet.');
    }

    public function testChefPoleCannotDeactivateAccountOutsideHisPole(): void
    {
        // CDC §3 : périmètre strict, voir UserVoterTest pour la logique unitaire.
        self::markTestIncomplete('UserController::deactivate() not implemented yet.');
    }
}
