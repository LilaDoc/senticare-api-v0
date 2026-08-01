<?php

namespace App\Tests\Integration;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Repository\PoleRepository;

/**
 * Test d'intégration : PoleRepository dialogue-t-il correctement avec la
 * vraie base Postgres ? Pas de logique métier ici (déjà couverte par
 * PoleManagerTest avec des doublures), juste "la requête est-elle correcte,
 * le mapping colonnes <-> objet fonctionne-t-il".
 */
class PoleRepositoryTest extends RepositoryIntegrationTestCase
{
    private PoleRepository $poleRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poleRepository = static::getContainer()->get(PoleRepository::class);
    }

    public function testFindReturnsNullForUnknownId(): void
    {
        self::assertNull($this->poleRepository->find('00000000-0000-0000-0000-000000000000'));
    }

    public function testPersistedPoleCanBeFoundById(): void
    {
        $pole = new Pole();
        $pole->setNom('Chirurgie');

        $this->entityManager->persist($pole);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->poleRepository->find($pole->getId());

        self::assertNotNull($found);
        self::assertSame('Chirurgie', $found->getNom());
    }

    public function testFindByUserReturnsOnlyPolesReachableViaUsersServices(): void
    {
        $poleAvecUtilisateur = new Pole();
        $poleAvecUtilisateur->setNom('Oncologie');

        $poleSansUtilisateur = new Pole();
        $poleSansUtilisateur->setNom('Maternité');

        $service = new Service();
        $service->setNom('Hôpital de jour');
        $service->setPole($poleAvecUtilisateur);

        $user = new User();
        $user->setEmail('cadre@test.fr');
        $user->setNom('Nom');
        $user->setPrenom('Prenom');
        $user->setRole(RoleEnum::Cadre);
        $user->setPassword('hash-non-pertinent-pour-ce-test');
        $user->addService($service);

        $this->entityManager->persist($poleAvecUtilisateur);
        $this->entityManager->persist($poleSansUtilisateur);
        $this->entityManager->persist($service);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $user = $this->entityManager->getRepository(User::class)->find($user->getId());
        $poles = $this->poleRepository->findByUser($user);

        self::assertCount(1, $poles);
        self::assertSame('Oncologie', $poles[0]->getNom());
    }
}
