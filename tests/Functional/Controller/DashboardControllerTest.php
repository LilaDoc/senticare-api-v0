<?php

namespace App\Tests\Functional\Controller;

use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC §4.6, US-3.1 : statistiques agrégées du tableau de bord superviseur.
 */
class DashboardControllerTest extends ApiTestCase
{
    public function testStatsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/dashboard/stats');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testSoignantCannotAccessStats(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $soignant = $this->createUser('soignant@test.fr', RoleEnum::Soignant, [$service]);
        $this->authenticateAs($soignant);

        $this->client->request('GET', '/api/dashboard/stats');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testStatsNeverGroupByIndividualDeclarant(): void
    {
        // Règle blameless impérative (CDC §2.1, §4.6) : jamais de classement par soignant.
        // TODO: une fois StatsManager::aggregate() implémenté, vérifier que la clé
        // "parDeclarant" / équivalent n'existe PAS dans la réponse JSON.
        self::markTestIncomplete('DashboardController::stats() not implemented yet.');
    }
}
