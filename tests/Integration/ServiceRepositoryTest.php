<?php

namespace App\Tests\Integration;

use App\Entity\Pole;
use App\Entity\Service;
use App\Repository\ServiceRepository;

/**
 * Test d'intégration (cf. 01_tests-2.md, niveau 2) : ServiceRepository
 * dialogue-t-il correctement avec la vraie base Postgres.
 */
class ServiceRepositoryTest extends RepositoryIntegrationTestCase
{
    private ServiceRepository $serviceRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceRepository = static::getContainer()->get(ServiceRepository::class);
    }

    public function testFindByPoleReturnsOnlyServicesOfThatPole(): void
    {
        $poleA = new Pole();
        $poleA->setNom('Chirurgie');
        $poleB = new Pole();
        $poleB->setNom('Oncologie');

        $serviceA1 = new Service();
        $serviceA1->setNom('Bloc opératoire');
        $serviceA1->setPole($poleA);

        $serviceA2 = new Service();
        $serviceA2->setNom('Chirurgie ambulatoire');
        $serviceA2->setPole($poleA);

        $serviceB = new Service();
        $serviceB->setNom('Hôpital de jour');
        $serviceB->setPole($poleB);

        foreach ([$poleA, $poleB, $serviceA1, $serviceA2, $serviceB] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $poleA = $this->entityManager->getRepository(Pole::class)->find($poleA->getId());
        $resultats = $this->serviceRepository->findByPole($poleA);

        self::assertCount(2, $resultats);
        self::assertEqualsCanonicalizing(
            ['Bloc opératoire', 'Chirurgie ambulatoire'],
            array_map(fn (Service $s) => $s->getNom(), $resultats)
        );
    }

    public function testFindByNameReturnsMatchingServices(): void
    {
        $pole = new Pole();
        $pole->setNom('Chirurgie');

        $service = new Service();
        $service->setNom('Bloc opératoire');
        $service->setPole($pole);

        $autreService = new Service();
        $autreService->setNom('Autre service');
        $autreService->setPole($pole);

        $this->entityManager->persist($pole);
        $this->entityManager->persist($service);
        $this->entityManager->persist($autreService);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $resultats = $this->serviceRepository->findByName('Bloc opératoire');

        self::assertCount(1, $resultats);
        self::assertSame('Bloc opératoire', $resultats[0]->getNom());
    }

    public function testFindByNameReturnsEmptyArrayWhenNoMatch(): void
    {
        self::assertSame([], $this->serviceRepository->findByName('Service inexistant'));
    }
}
