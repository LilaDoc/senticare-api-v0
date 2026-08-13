<?php

namespace App\Tests\Integration;

use App\Service\ReferenceGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration : la génération de référence s'appuie sur une séquence
 * PostgreSQL, elle ne peut donc pas être vérifiée par un test unitaire.
 *
 * C'est précisément le point qui justifie la séquence plutôt qu'un
 * COUNT(*) + 1 : deux déclarations créées coup sur coup doivent recevoir deux
 * références distinctes. Avec un COUNT, deux soignants qui déclarent en même
 * temps liraient le même total, généreraient la même référence, et l'une des
 * deux déclarations serait rejetée par la contrainte d'unicité — un
 * signalement perdu pour un problème de numérotation.
 */
class ReferenceGeneratorTest extends KernelTestCase
{
    private ReferenceGenerator $generator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->generator = self::getContainer()->get(ReferenceGenerator::class);
    }

    public function testGenerateProducesReadableReference(): void
    {
        $reference = $this->generator->generate(new \DateTimeImmutable('2026-08-05'));

        self::assertMatchesRegularExpression('/^DCL-2026-\d{4,}$/', $reference);
    }

    public function testConsecutiveCallsNeverProduceTheSameReference(): void
    {
        $references = [];
        for ($i = 0; $i < 5; ++$i) {
            $references[] = $this->generator->generate();
        }

        self::assertCount(5, array_unique($references));
    }

    public function testYearComesFromTheProvidedDate(): void
    {
        // L'année est celle de la déclaration, pas celle du serveur au moment
        // du calcul — important pour le rattrapage de données existantes.
        self::assertStringStartsWith('DCL-2025-', $this->generator->generate(new \DateTimeImmutable('2025-12-31')));
    }
}
