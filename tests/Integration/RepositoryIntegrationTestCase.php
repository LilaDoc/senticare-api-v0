<?php

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base commune aux tests d'intégration de Repository (cf. 01_tests-2.md,
 * niveau 2 de la pyramide) : "le code sait-il dialoguer avec UNE ressource
 * technique précise" — ici Doctrine + la vraie base Postgres de test.
 *
 * KernelTestCase (PAS WebTestCase) : on démarre le conteneur Symfony pour
 * récupérer les VRAIS services (EntityManager, Repository...), mais sans
 * client HTTP ni requête — on appelle le Repository directement, comme
 * dans le cours. C'est ce qui distingue ce niveau du niveau fonctionnel
 * (ApiTestCase), qui passe par une vraie route HTTP.
 *
 * Pourquoi KernelTestCase et pas un EntityManager construit "à la main" :
 * nos entités déclarent #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
 * — un identifiant de service Symfony, pas un nom de classe PHP. Seul le
 * conteneur sait le résoudre ; en dehors de Symfony, Doctrine ne peut pas
 * instancier le générateur d'UUID. Le conteneur est donc requis ici, pas
 * par goût mais parce que le mapping de nos entités en dépend.
 *
 * La transaction annulée automatiquement (DAMADoctrineTestBundle) continue
 * de fonctionner normalement ici, puisqu'on utilise la vraie connexion du
 * conteneur — rien à gérer manuellement, contrairement à une connexion
 * construite à la main.
 */
abstract class RepositoryIntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }
}
