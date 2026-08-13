<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Declaration;
use App\Enum\GraviteEnum;
use App\Enum\RoleEnum;
use App\Enum\TypeEIEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC UC-01 à UC-07.
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
                // Le contrôleur attend la clé "service", pas "serviceId".
                'service' => (string) $service->getId(),
                'typeEI' => 'chute',
                'dateConstat' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'dateSurvenue' => (new \DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
                // Aucune des 3 questions EIGS positive -> choix manuel requis
                // (DeclarationManager::calculerGravite(), CDC §4.2).
                'choixSiNonEIGS' => 'mineur',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('brouillon', $data['statut']['value']);
        self::assertSame((string) $soignant->getId(), $data['declarant']['id']);
    }

    public function testCreatedDeclarationExposesReadableReference(): void
    {
        // La référence lisible (DCL-2026-0042) est le seul identifiant citable :
        // un UUID ne se dicte pas au téléphone et ne s'inscrit pas sur un compte
        // rendu de revue. Son unicité sous concurrence est vérifiée séparément
        // par ReferenceGeneratorTest.
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $soignant = $this->createUser('referent@test.fr', RoleEnum::Soignant, [$service]);
        $this->authenticateAs($soignant);

        $this->client->request(
            'POST',
            '/api/declarations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'service' => (string) $service->getId(),
                'typeEI' => 'chute',
                'dateConstat' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'dateSurvenue' => (new \DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
                'choixSiNonEIGS' => 'mineur',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertMatchesRegularExpression('/^DCL-\d{4}-\d{4,}$/', $data['reference']);
    }

    public function testDeclarationCreationFailsInTheFuture(): void
    {
        // CDC §4.3 : dateConstat ne peut pas être dans le futur.
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $soignant = $this->createUser('soignant2@test.fr', RoleEnum::Soignant, [$service]);
        $this->authenticateAs($soignant);

        $this->client->request(
            'POST',
            '/api/declarations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'service' => (string) $service->getId(),
                'typeEI' => 'chute',
                'dateConstat' => (new \DateTimeImmutable('+1 day'))->format(DATE_ATOM),
                'dateSurvenue' => (new \DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
                'choixSiNonEIGS' => 'mineur',
            ])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testOnlyDeclarantCanSubmitOwnDraft(): void
    {
        // CDC UC-05 : soumission uniquement par le déclarant, depuis le statut brouillon.
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $declarant = $this->createUser('declarant@test.fr', RoleEnum::Soignant, [$service]);
        $autreSoignant = $this->createUser('autre-soignant@test.fr', RoleEnum::Soignant, [$service]);

        $declaration = new Declaration();
        // `reference` est NOT NULL et unique : normalement posée par
        // DeclarationManager::createDraft(), à fournir ici puisqu'on construit
        // l'entité à la main pour partir d'un brouillon existant.
        $declaration->setReference(self::uniqueReference());
        $declaration->setDeclarant($declarant);
        $declaration->setService($service);
        $declaration->setTypeEI(TypeEIEnum::Chute);
        $declaration->setDateConstat(new \DateTimeImmutable('-1 day'));
        $declaration->setDateSurvenue(new \DateTimeImmutable('-1 day'));
        $declaration->setGravite(GraviteEnum::Mineur);
        $this->entityManager->persist($declaration);
        $this->entityManager->flush();
        $declarationId = (string) $declaration->getId();

        $this->authenticateAs($autreSoignant);
        $this->client->request('POST', '/api/declarations/'.$declarationId.'/submit');
        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testDeclarantCanSubmitOwnDraft(): void
    {
        $pole = $this->createPole();
        $service = $this->createService($pole);
        $declarant = $this->createUser('declarant2@test.fr', RoleEnum::Soignant, [$service]);

        $declaration = new Declaration();
        // `reference` est NOT NULL et unique : normalement posée par
        // DeclarationManager::createDraft(), à fournir ici puisqu'on construit
        // l'entité à la main pour partir d'un brouillon existant.
        $declaration->setReference(self::uniqueReference());
        $declaration->setDeclarant($declarant);
        $declaration->setService($service);
        $declaration->setTypeEI(TypeEIEnum::Chute);
        $declaration->setDateConstat(new \DateTimeImmutable('-1 day'));
        $declaration->setDateSurvenue(new \DateTimeImmutable('-1 day'));
        $declaration->setGravite(GraviteEnum::Mineur);
        $this->entityManager->persist($declaration);
        $this->entityManager->flush();
        $declarationId = (string) $declaration->getId();

        $this->authenticateAs($declarant);
        $this->client->request('POST', '/api/declarations/'.$declarationId.'/submit');

        self::assertResponseIsSuccessful();
        self::assertSame('soumise', json_decode($this->client->getResponse()->getContent(), true)['statut']['value']);
    }

    /** Référence unique par test — la vraie numérotation vient d'une séquence PostgreSQL. */
    private static function uniqueReference(): string
    {
        return 'DCL-TEST-'.bin2hex(random_bytes(4));
    }
}
