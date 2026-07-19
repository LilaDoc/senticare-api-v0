<?php

namespace App\Tests\Functional\Controller;

use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC §4.5, US-3.3 : consultation des fiches RMM du périmètre.
 */
class RmmControllerTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/rmm');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testSoignantCannotAccessRmmList(): void
    {
        // ROLE_CADRE minimum requis (hérité par chef de pôle) — le soignant seul n'y accède pas.
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $soignant = $this->createUser('soignant@test.fr', RoleEnum::Soignant, [$service]);
        $this->authenticateAs($soignant);

        $this->client->request('GET', '/api/rmm');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testCadreCanReachRmmEndpoint(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $cadre = $this->createUser('cadre@test.fr', RoleEnum::Cadre, [$service]);
        $this->authenticateAs($cadre);

        $this->client->request('GET', '/api/rmm');

        // TODO: une fois RmmController::list() implémenté, attendre 200 + fiches du périmètre uniquement.
        self::assertNotSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }
}
