<?php

namespace App\Tests\Unit\Service;

use App\Entity\Pole;
use App\Entity\User;
use App\Repository\DeclarationRepository;
use App\Service\DeclarationManager;
use App\Service\StatsManager;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire (cf. 01_tests-2.md, niveau 1). DeclarationManager n'est
 * utilisé ici que pour resolvePerimeterCriteria() — doublé en Mock pour
 * contrôler ce qu'il renvoie, sans dépendre de sa propre logique de rôles
 * (déjà testée dans DeclarationManagerTest).
 */
class StatsManagerTest extends TestCase
{
    public function testAggregateDelegatesToRepositoryWithResolvedCriteria(): void
    {
        $requester = new User();
        $requester->setEmail('chef@test.fr');
        $requester->setPassword('hash');

        $pole = new Pole();
        $pole->setNom('Chirurgie');
        $criteria = ['pole' => $pole];
        $filters = ['dateFrom' => new \DateTimeImmutable('-1 month')];
        $resultatAttendu = ['parService' => [], 'parType' => [], 'parPeriode' => []];

        $declarationManager = $this->createMock(DeclarationManager::class);
        $declarationManager->expects($this->once())
            ->method('resolvePerimeterCriteria')
            ->with($requester)
            ->willReturn($criteria);

        $declarationRepository = $this->createMock(DeclarationRepository::class);
        $declarationRepository->expects($this->once())
            ->method('aggregate')
            ->with($criteria, $filters)
            ->willReturn($resultatAttendu);

        $manager = new StatsManager($declarationRepository, $declarationManager);

        $result = $manager->aggregate($requester, $filters);

        self::assertSame($resultatAttendu, $result);
    }

    public function testAggregateThrowsAndNeverQueriesRepositoryWhenCriteriaContainsDeclarant(): void
    {
        // Règle blameless (CDC §2.1/§4.6) : ne doit jamais atteindre l'agrégation.
        $requester = new User();
        $requester->setEmail('soignant@test.fr');
        $requester->setPassword('hash');

        $declarationManager = $this->createStub(DeclarationManager::class);
        $declarationManager->method('resolvePerimeterCriteria')->willReturn(['declarant' => $requester]);

        $declarationRepository = $this->createMock(DeclarationRepository::class);
        $declarationRepository->expects($this->never())->method('aggregate');

        $manager = new StatsManager($declarationRepository, $declarationManager);

        $this->expectException(\RuntimeException::class);

        $manager->aggregate($requester);
    }
}
