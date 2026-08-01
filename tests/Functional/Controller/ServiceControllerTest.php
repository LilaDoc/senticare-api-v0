<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Service;
use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC UC-13 : gestion des services partagée entre admin (tous pôles) et chef
 * de pôle (son pôle uniquement).
 *
 * Contrairement à PoleControllerTest, il n'y a pas de #[IsGranted('ROLE_...')]
 * au niveau classe ici (cf. docblock de ServiceController) : la protection de
 * périmètre vient entièrement de ServiceVoter, appelé à l'intérieur de chaque
 * méthode (list/show/create/update/deactivate).
 */
class ServiceControllerTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/services');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testCadreCannotAccessServicesEndpoint(): void
    {
        // CDC UC-13 : seuls admin et chef de pôle gèrent les services — le cadre non.
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $cadre = $this->createUser('cadre@test.fr', RoleEnum::Cadre, [$service]);
        $this->authenticateAs($cadre);

        $this->client->request('GET', '/api/services');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCanCreateServiceAttachedToAPole(): void
    {
        $pole = $this->createPole();
        $admin = $this->createUser('admin@test.fr', RoleEnum::Admin);
        $this->authenticateAs($admin);

        $this->client->request(
            'POST',
            '/api/services',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['nom' => 'Néonatalogie', 'poleId' => (string) $pole->getId()])
        );

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $service = $this->entityManager->getRepository(Service::class)->find($data['id']);

        self::assertSame((string) $pole->getId(), (string) $service->getPole()->getId());
    }

    public function testChefPoleCanCreateServiceInHisOwnPole(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $chefPole = $this->createUser('chefpole@test.fr', RoleEnum::ChefPole, [$service]);
        $this->authenticateAs($chefPole);

        $this->client->request(
            'POST',
            '/api/services',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['nom' => 'Chirurgie ambulatoire', 'poleId' => (string) $pole->getId()])
        );

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
    }

    public function testChefPoleCannotCreateServiceInAnotherPole(): void
    {
        $sonPole = $this->createPole('Chirurgie');
        $sonService = $this->createService($sonPole);
        $chefPole = $this->createUser('chefpole2@test.fr', RoleEnum::ChefPole, [$sonService]);
        $this->authenticateAs($chefPole);

        $autrePole = $this->createPole('Oncologie');

        $this->client->request(
            'POST',
            '/api/services',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['nom' => 'Hôpital de jour', 'poleId' => (string) $autrePole->getId()])
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testChefPoleCannotDeactivateServiceOfAnotherPole(): void
    {
        // deactivate() est câblé sur ServiceVoter, désormais implémenté — un
        // chef de pôle ne doit pas pouvoir désactiver le service d'un autre pôle.
        $sonPole = $this->createPole('Chirurgie');
        $sonService = $this->createService($sonPole);
        $chefPole = $this->createUser('chefpole3@test.fr', RoleEnum::ChefPole, [$sonService]);
        $this->authenticateAs($chefPole);

        $autrePole = $this->createPole('Oncologie');
        $autreService = $this->createService($autrePole);

        $this->client->request('PATCH', '/api/services/'.$autreService->getId().'/deactivate');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testChefPoleCanDeactivateServiceOfHisOwnPole(): void
    {
        $pole = $this->createPole('Chirurgie');
        $service = $this->createService($pole);
        $chefPole = $this->createUser('chefpole4@test.fr', RoleEnum::ChefPole, [$service]);
        $this->authenticateAs($chefPole);

        $this->client->request('PATCH', '/api/services/'.$service->getId().'/deactivate');

        self::assertResponseIsSuccessful();
        self::assertFalse(json_decode($this->client->getResponse()->getContent(), true)['isActive']);
    }
}
