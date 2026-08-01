<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
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

        // L'envoi effectif de l'email (contenu, mot de passe provisoire) est
        // déjà couvert par NotificationManagerTest — ici on vérifie la
        // chaîne HTTP -> persistance réelle.
        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $nouveauSoignant = $this->entityManager->getRepository(User::class)->find($data['id']);

        self::assertSame('nouveau.soignant@test.fr', $nouveauSoignant->getEmail());
        self::assertContains(RoleEnum::Soignant->value, $nouveauSoignant->getRoles());
        self::assertTrue($nouveauSoignant->getServices()->contains($service));
    }

    public function testChefPoleCannotCreateChefPoleAccount(): void
    {
        // CDC §3 : seul l'admin crée des comptes chefs de pôle.
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $chefPole = $this->createUser('chefpole@test.fr', RoleEnum::ChefPole, [$service]);
        $this->authenticateAs($chefPole);

        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'autre.chefpole@test.fr',
                'nom' => 'Martin',
                'prenom' => 'Julie',
                'role' => 'ROLE_CHEF_POLE',
                'serviceIds' => [],
            ])
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCanCreateChefPoleAccount(): void
    {
        $admin = $this->createUser('admin@test.fr', RoleEnum::Admin);
        $this->authenticateAs($admin);

        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'nouveau.chefpole@test.fr',
                'nom' => 'Martin',
                'prenom' => 'Julie',
                'role' => 'ROLE_CHEF_POLE',
                'serviceIds' => [],
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
    }

    public function testChefPoleCannotDeactivateAccountOutsideHisPole(): void
    {
        // CDC §3 : périmètre strict, voir UserVoterTest pour la logique unitaire.
        $sonPole = $this->createPole('Chirurgie');
        $sonService = $this->createService($sonPole);
        $chefPole = $this->createUser('chefpole@test.fr', RoleEnum::ChefPole, [$sonService]);
        $this->authenticateAs($chefPole);

        $autrePole = $this->createPole('Oncologie');
        $autreService = $this->createService($autrePole);
        $soignantHorsPerimetre = $this->createUser('soignant.horspole@test.fr', RoleEnum::Soignant, [$autreService]);

        $this->client->request('PATCH', '/api/users/'.$soignantHorsPerimetre->getId().'/deactivate');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
