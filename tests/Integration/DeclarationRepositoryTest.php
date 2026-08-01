<?php

namespace App\Tests\Integration;

use App\Entity\Declaration;
use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\RoleEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Repository\DeclarationRepository;

/**
 * Test d'intégration (cf. 01_tests-2.md, niveau 2) : DeclarationRepository
 * dialogue-t-il correctement avec la vraie base Postgres — search() (UC-06)
 * et aggregate() (tableau de bord, CDC §4.6).
 */
class DeclarationRepositoryTest extends RepositoryIntegrationTestCase
{
    private DeclarationRepository $declarationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->declarationRepository = static::getContainer()->get(DeclarationRepository::class);
    }

    private function persistUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setNom('Nom');
        $user->setPrenom('Prenom');
        $user->setRole(RoleEnum::Soignant);
        $user->setPassword('hash-non-pertinent-pour-ce-test');

        $this->entityManager->persist($user);

        return $user;
    }

    private function persistService(string $nom, ?Pole $pole = null): Service
    {
        $pole ??= (function (): Pole {
            $pole = new Pole();
            $pole->setNom('Pôle '.uniqid());
            $this->entityManager->persist($pole);

            return $pole;
        })();

        $service = new Service();
        $service->setNom($nom);
        $service->setPole($pole);
        $this->entityManager->persist($service);

        return $service;
    }

    private function persistDeclaration(
        User $declarant,
        Service $service,
        TypeEIEnum $typeEI = TypeEIEnum::Chute,
        GraviteEnum $gravite = GraviteEnum::Mineur,
        StatutEnum $statut = StatutEnum::Brouillon,
    ): Declaration {
        $declaration = new Declaration();
        $declaration->setDeclarant($declarant);
        $declaration->setService($service);
        $declaration->setTypeEI($typeEI);
        $declaration->setDateConstat(new \DateTimeImmutable('-1 day'));
        $declaration->setDateSurvenue(new \DateTimeImmutable('-1 day'));
        $declaration->setGravite($gravite);
        $declaration->setStatut($statut);

        $this->entityManager->persist($declaration);

        return $declaration;
    }

    // --- search() ---

    public function testSearchFiltersByDeclarant(): void
    {
        $service = $this->persistService('Bloc opératoire');
        $declarant = $this->persistUser('soignant1@test.fr');
        $autreDeclarant = $this->persistUser('soignant2@test.fr');

        $this->persistDeclaration($declarant, $service);
        $this->persistDeclaration($autreDeclarant, $service);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $declarant = $this->entityManager->getRepository(User::class)->find($declarant->getId());
        $resultats = $this->declarationRepository->search(['declarant' => $declarant], []);

        self::assertCount(1, $resultats);
        self::assertSame($declarant->getId(), $resultats[0]->getDeclarant()->getId());
    }

    public function testSearchFiltersByServices(): void
    {
        $serviceAutorise = $this->persistService('Bloc opératoire');
        $autreService = $this->persistService('Hôpital de jour');
        $declarant = $this->persistUser('soignant@test.fr');

        $this->persistDeclaration($declarant, $serviceAutorise);
        $this->persistDeclaration($declarant, $autreService);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $serviceAutorise = $this->entityManager->getRepository(Service::class)->find($serviceAutorise->getId());
        $resultats = $this->declarationRepository->search(['services' => [$serviceAutorise]], []);

        self::assertCount(1, $resultats);
        self::assertSame($serviceAutorise->getId(), $resultats[0]->getService()->getId());
    }

    public function testSearchFiltersByPole(): void
    {
        $poleAutorise = new Pole();
        $poleAutorise->setNom('Chirurgie');
        $autrePole = new Pole();
        $autrePole->setNom('Oncologie');
        $this->entityManager->persist($poleAutorise);
        $this->entityManager->persist($autrePole);

        $serviceDuPoleAutorise = $this->persistService('Bloc opératoire', $poleAutorise);
        $serviceDeLAutrePole = $this->persistService('Hôpital de jour', $autrePole);
        $declarant = $this->persistUser('soignant@test.fr');

        $this->persistDeclaration($declarant, $serviceDuPoleAutorise);
        $this->persistDeclaration($declarant, $serviceDeLAutrePole);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $poleAutorise = $this->entityManager->getRepository(Pole::class)->find($poleAutorise->getId());
        $resultats = $this->declarationRepository->search(['pole' => $poleAutorise], []);

        self::assertCount(1, $resultats);
    }

    /**
     * Sécurité (cf. docs/REVISION_ORAL.md §4) : un chef de pôle mal
     * configuré (aucun service) a resolvePerimeterCriteria() qui renvoie
     * ['pole' => null]. array_key_exists('pole', ...) applique quand même
     * le filtre (s.pole = NULL, qui ne matche jamais rien en SQL) —
     * fail-closed, jamais fail-open.
     */
    public function testSearchWithNullPoleMatchesNothingRatherThanEverything(): void
    {
        $service = $this->persistService('Bloc opératoire');
        $declarant = $this->persistUser('soignant@test.fr');
        $this->persistDeclaration($declarant, $service);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $resultats = $this->declarationRepository->search(['pole' => null], []);

        self::assertSame([], $resultats);
    }

    public function testSearchFiltersByStatutTypeGraviteAndEigsOnly(): void
    {
        $service = $this->persistService('Bloc opératoire');
        $declarant = $this->persistUser('soignant@test.fr');

        $cherchee = $this->persistDeclaration($declarant, $service, TypeEIEnum::Chute, GraviteEnum::Deces, StatutEnum::Soumise);
        $this->persistDeclaration($declarant, $service, TypeEIEnum::Infection, GraviteEnum::Mineur, StatutEnum::Brouillon);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $resultats = $this->declarationRepository->search([], [
            'statut' => StatutEnum::Soumise,
            'typeEI' => TypeEIEnum::Chute,
            'gravite' => GraviteEnum::Deces,
            'eigsOnly' => true,
        ]);

        self::assertCount(1, $resultats);
        self::assertSame((string) $cherchee->getId(), (string) $resultats[0]->getId());
    }

    public function testSearchFiltersByMotCle(): void
    {
        $service = $this->persistService('Bloc opératoire');
        $declarant = $this->persistUser('soignant@test.fr');

        $avecDescription = $this->persistDeclaration($declarant, $service);
        $avecDescription->setDescription('Chute du patient dans le couloir');

        $sansLeMotCle = $this->persistDeclaration($declarant, $service);
        $sansLeMotCle->setDescription('Erreur de dosage médicamenteux');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $resultats = $this->declarationRepository->search([], ['motCle' => 'couloir']);

        self::assertCount(1, $resultats);
        self::assertSame((string) $avecDescription->getId(), (string) $resultats[0]->getId());
    }

    // --- aggregate() ---

    public function testAggregateGroupsCountsByServiceAndType(): void
    {
        $serviceA = $this->persistService('Bloc opératoire');
        $serviceB = $this->persistService('Hôpital de jour');
        $declarant = $this->persistUser('soignant@test.fr');

        $this->persistDeclaration($declarant, $serviceA, TypeEIEnum::Chute);
        $this->persistDeclaration($declarant, $serviceA, TypeEIEnum::Chute);
        $this->persistDeclaration($declarant, $serviceB, TypeEIEnum::Infection);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $stats = $this->declarationRepository->aggregate([], []);

        $parService = array_column($stats['parService'], 'total', 'service');
        self::assertSame(2, (int) $parService['Bloc opératoire']);
        self::assertSame(1, (int) $parService['Hôpital de jour']);

        $totalParType = array_sum(array_column($stats['parType'], 'total'));
        self::assertSame(3, $totalParType);
        self::assertNotEmpty($stats['parPeriode']);
    }
}
