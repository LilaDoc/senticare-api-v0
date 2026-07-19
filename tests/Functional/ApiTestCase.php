<?php

namespace App\Tests\Functional;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base commune aux tests fonctionnels de contrôleurs API.
 *
 * Fournit des helpers pour construire rapidement la structure minimale
 * (pôle / service / utilisateur) nécessaire aux tests de périmètre (CDC §3).
 * Chaque test tourne dans une transaction annulée automatiquement en fin de
 * test (DAMADoctrineTestBundle) — inutile de nettoyer la base manuellement.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createPole(string $nom = 'Pôle Test'): Pole
    {
        $pole = new Pole();
        $pole->setNom($nom);
        $this->entityManager->persist($pole);
        $this->entityManager->flush();

        return $pole;
    }

    protected function createService(Pole $pole, string $nom = 'Service Test'): Service
    {
        $service = new Service();
        $service->setNom($nom);
        $service->setPole($pole);
        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $service;
    }

    /**
     * @param Service[] $services Convention (cf. AppFixtures) : un chef de pôle est
     *                            rattaché à tous les services de son pôle — il n'existe
     *                            pas de relation directe User -> Pole dans le modèle actuel.
     */
    protected function createUser(string $email, RoleEnum $role, array $services = []): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setNom('Nom');
        $user->setPrenom('Prenom');
        $user->setRoles([$role->value]);
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        foreach ($services as $service) {
            $user->addService($service);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Authentifie le client de test comme l'utilisateur donné, sur le firewall "api"
     * (stateless) — loginUser() fonctionne nativement avec les firewalls stateless.
     */
    protected function authenticateAs(User $user): void
    {
        $this->client->loginUser($user, 'api');
    }
}
