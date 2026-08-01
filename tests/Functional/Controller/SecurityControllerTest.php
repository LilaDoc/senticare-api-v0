<?php

namespace App\Tests\Functional\Controller;

use App\Enum\RoleEnum;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDC §6.1 : flux d'authentification JWT complet — login, /api/me,
 * rafraîchissement de token. Les connexions elles-mêmes (succès/échec) sont
 * déjà testées via LoginLogSubscriberTest (journal d'audit) ; ici on
 * vérifie le flux HTTP bout en bout : tokens renvoyés, rotation du refresh
 * token, protections anti-énumération.
 */
class SecurityControllerTest extends ApiTestCase
{
    public function testLoginWithValidCredentialsReturnsTokenAndRefreshToken(): void
    {
        $this->createUser('soignant@test.fr', RoleEnum::Soignant);

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'soignant@test.fr', 'password' => 'Test1234!'])
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('refresh_token', $data);
    }

    public function testLoginWithWrongPasswordReturnsGenericError(): void
    {
        $this->createUser('soignant@test.fr', RoleEnum::Soignant);

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'soignant@test.fr', 'password' => 'MauvaisMotDePasse!'])
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            json_decode($this->client->getResponse()->getContent(), true)['message'] ?? null,
            self::loginWithUnknownEmailErrorMessage()
        );
    }

    public function testLoginWithUnknownEmailReturnsSameGenericErrorAsWrongPassword(): void
    {
        // CDC §6.1 : message générique dans tous les cas — aucune distinction
        // entre "mauvais mot de passe" et "compte inexistant" (anti-énumération).
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'inconnu@test.fr', 'password' => 'PeuImporte1!'])
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            self::loginWithUnknownEmailErrorMessage(),
            json_decode($this->client->getResponse()->getContent(), true)['message'] ?? null
        );
    }

    private static function loginWithUnknownEmailErrorMessage(): string
    {
        return 'Invalid credentials.';
    }

    public function testMeRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testMeReturnsIdentityAndRolesForAuthenticatedUser(): void
    {
        $soignant = $this->createUser('soignant@test.fr', RoleEnum::Soignant);
        $this->authenticateAs($soignant);

        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('soignant@test.fr', $data['email']);
        self::assertContains('ROLE_SOIGNANT', $data['roles']);
    }

    public function testTokenRefreshReturnsNewTokenAndRotatesRefreshToken(): void
    {
        $this->createUser('soignant@test.fr', RoleEnum::Soignant);

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'soignant@test.fr', 'password' => 'Test1234!'])
        );
        $login = json_decode($this->client->getResponse()->getContent(), true);

        $this->client->request(
            'POST',
            '/api/token/refresh',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['refresh_token' => $login['refresh_token']])
        );

        self::assertResponseIsSuccessful();

        $refreshed = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $refreshed);
        self::assertArrayHasKey('refresh_token', $refreshed);
        // Rotation (single_use: true, gesdinet_jwt_refresh_token.yaml) : un
        // nouveau refresh_token est émis à chaque utilisation.
        self::assertNotSame($login['refresh_token'], $refreshed['refresh_token']);
    }

    public function testUsedRefreshTokenCannotBeReusedAgain(): void
    {
        // single_use: true — un refresh token déjà consommé doit être refusé
        // si on tente de le réutiliser (vol de token, rejeu).
        $this->createUser('soignant@test.fr', RoleEnum::Soignant);

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'soignant@test.fr', 'password' => 'Test1234!'])
        );
        $refreshToken = json_decode($this->client->getResponse()->getContent(), true)['refresh_token'];

        // Première utilisation : consomme le token (rotation).
        $this->client->request(
            'POST',
            '/api/token/refresh',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['refresh_token' => $refreshToken])
        );
        self::assertResponseIsSuccessful();

        // Deuxième utilisation du MÊME refresh token : doit être refusée.
        $this->client->request(
            'POST',
            '/api/token/refresh',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['refresh_token' => $refreshToken])
        );
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }
}
