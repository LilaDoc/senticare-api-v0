<?php

namespace App\Tests\Unit\Service;

use App\Enum\GraviteEnum;
use App\Service\DeclarationManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Assistant de qualification de la gravité en 3 questions (CDC §4.2 — formulaire
 * HAS EIGS). Seule méthode de DeclarationManager déjà implémentée : ce test doit
 * passer dès maintenant (vert), contrairement aux autres tests de ce squelette.
 */
class DeclarationManagerGraviteTest extends TestCase
{
    private DeclarationManager $manager;

    protected function setUp(): void
    {
        // Les autres dépendances ne sont pas utilisées par calculerGravite() —
        // des mocks vides suffisent ici (test unitaire pur, pas de kernel/DB).
        $this->manager = new DeclarationManager(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(\App\Repository\DeclarationRepository::class),
            $this->createStub(\App\Service\NotificationManager::class),
            $this->createStub(\App\Service\LogManager::class),
        );
    }

    public function testDecesEstPrioritaireSurLesAutresCriteres(): void
    {
        self::assertSame(
            GraviteEnum::Deces,
            $this->manager->calculerGravite(deces: true, pronosticVitalEnJeu: true, risqueDeficitFonctionnelPermanent: true)
        );
    }

    public function testPronosticVitalEnJeuSansDeces(): void
    {
        self::assertSame(
            GraviteEnum::Critique,
            $this->manager->calculerGravite(deces: false, pronosticVitalEnJeu: true, risqueDeficitFonctionnelPermanent: true)
        );
    }

    public function testRisqueDeficitFonctionnelSeul(): void
    {
        self::assertSame(
            GraviteEnum::Grave,
            $this->manager->calculerGravite(deces: false, pronosticVitalEnJeu: false, risqueDeficitFonctionnelPermanent: true)
        );
    }

    public function testAucunCritereEIGSAvecChoixManuelMineur(): void
    {
        self::assertSame(
            GraviteEnum::Mineur,
            $this->manager->calculerGravite(false, false, false, GraviteEnum::Mineur)
        );
    }

    public function testAucunCritereEIGSAvecChoixManuelModere(): void
    {
        self::assertSame(
            GraviteEnum::Modere,
            $this->manager->calculerGravite(false, false, false, GraviteEnum::Modere)
        );
    }

    public function testAucunCritereEIGSSansChoixManuelLeveUneException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->calculerGravite(false, false, false);
    }

    public function testChoixManuelIgnoreSiUneQuestionEIGSEstPositive(): void
    {
        // Le choix manuel ne doit jamais pouvoir "rétrograder" un EIGS.
        self::assertSame(
            GraviteEnum::Grave,
            $this->manager->calculerGravite(false, false, true, GraviteEnum::Mineur)
        );
    }
}
