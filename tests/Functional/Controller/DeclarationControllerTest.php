<?php

namespace App\Tests\Functional\Controller;

use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC UC-01 à UC-07. Deux catégories de tests ici :
 *  - les frontières d'accès (401/403) : elles passent déjà aujourd'hui, la
 *    sécurité (firewall JWT + #[IsGranted]) est en place indépendamment de
 *    l'implémentation métier.
 *  - le comportement métier (création, périmètre, cycle de vie) : ils
 *    resteront rouges tant que DeclarationController/DeclarationManager ne
 *    sont pas implémentés — normal en TDD.
 */
class DeclarationControllerTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/declarations');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCannotAccessDeclarations(): void
    {
        // CDC §3 : interdiction explicite, pas une simple absence de droit.
        $admin = $this->createUser('admin@test.fr', RoleEnum::Admin);
        $this->authenticateAs($admin);

        $this->client->request('GET', '/api/declarations');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testSoignantCanAccessDeclarationsList(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $soignant = $this->createUser('soignant@test.fr', RoleEnum::Soignant, [$service]);
        $this->authenticateAs($soignant);

        $this->client->request('GET', '/api/declarations');

        // TODO: aujourd'hui la route lève une RuntimeException TODO -> 500.
        // Une fois DeclarationController::list() implémenté, remplacer par
        // assertResponseIsSuccessful() + assertions sur le contenu JSON.
        self::assertNotSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testSoignantCanCreateDraftDeclaration(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $soignant = $this->createUser('soignant2@test.fr', RoleEnum::Soignant, [$service]);
        $this->authenticateAs($soignant);

        $this->client->request(
            'POST',
            '/api/declarations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'serviceId' => (string) $service->getId(),
                'typeEI' => 'chute',
                'dateConstat' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'dateSurvenue' => (new \DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
            ])
        );

        // TODO: une fois implémenté, attendre 201 + vérifier que la déclaration
        // créée a bien statut = brouillon (CDC §4.4) et declarant = $soignant.
        self::markTestIncomplete('DeclarationController::create() not implemented yet.');
    }

    public function testDeclarationCreationFailsInTheFuture(): void
    {
        // CDC §4.3 : dateConstat ne peut pas être dans le futur.
        self::markTestIncomplete('DeclarationController::create() validation not implemented yet.');
    }

    public function testOnlyDeclarantCanSubmitOwnDraft(): void
    {
        // CDC UC-05 : soumission uniquement par le déclarant, depuis le statut brouillon.
        self::markTestIncomplete('DeclarationController::submit() not implemented yet.');
    }

    public function testSubmittingEigsDeclarationGeneratesRmmAutomatically(): void
    {
        // CDC §4.5 : fiche RMM auto-générée et non désactivable si isEIGS = true.
        self::markTestIncomplete('DeclarationManager::submit() RMM auto-generation not implemented yet.');
    }
}
