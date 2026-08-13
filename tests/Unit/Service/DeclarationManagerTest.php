<?php

namespace App\Tests\Unit\Service;

use App\Entity\Declaration;
use App\Entity\Pole;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Repository\DeclarationRepository;
use App\Service\DeclarationManager;
use App\Service\LogManager;
use App\Service\NotificationManager;
use App\Service\ReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Test unitaire (cf. 01_tests-2.md, niveau 1) du cycle de vie complet —
 * calculerGravite() est déjà couvert en profondeur par
 * DeclarationManagerGraviteTest, pas reproduit ici.
 */
class DeclarationManagerTest extends TestCase
{
    private function createManager(
        ?EntityManagerInterface $entityManager = null,
        ?DeclarationRepository $declarationRepository = null,
        ?NotificationManager $notificationManager = null,
        ?LogManager $logManager = null,
        ?ValidatorInterface $validator = null,
    ): DeclarationManager {
        return new DeclarationManager(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $declarationRepository ?? $this->createStub(DeclarationRepository::class),
            $notificationManager ?? $this->createStub(NotificationManager::class),
            $logManager ?? $this->createStub(LogManager::class),
            // Vrai validateur (léger, pas besoin du kernel) plutôt qu'un stub :
            // on veut vraiment exercer la contrainte #[Assert\Length] déclarée
            // sur Declaration::$description, pas la simuler.
            $validator ?? Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            // Stub : la génération de référence s'appuie sur une séquence
            // PostgreSQL, hors de portée d'un test unitaire. On vérifie
            // seulement que le manager en pose une (cf. testCreateDraftAssignsReference).
            $this->createConfiguredStub(ReferenceGenerator::class, ['generate' => 'DCL-2026-0001']),
        );
    }

    private function createSoignant(ServiceEntity $service): User
    {
        $soignant = new User();
        $soignant->setEmail('soignant@test.fr');
        $soignant->setNom('Nom');
        $soignant->setPrenom('Prenom');
        $soignant->setRole(RoleEnum::Soignant);
        $soignant->setPassword('hash-non-pertinent-pour-ce-test');
        $soignant->addService($service);

        return $soignant;
    }

    private function createService(): ServiceEntity
    {
        $pole = new Pole();
        $pole->setNom('Chirurgie');

        $service = new ServiceEntity();
        $service->setNom('Bloc opératoire');
        $service->setPole($pole);

        return $service;
    }

    // --- createDraft() ---

    public function testCreateDraftSetsGraviteAndIsEIGSFromReponses(): void
    {
        $service = $this->createService();
        $declarant = $this->createSoignant($service);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Declaration::class));
        $entityManager->expects($this->once())->method('flush');

        $manager = $this->createManager(entityManager: $entityManager);

        $declaration = $manager->createDraft(
            $declarant,
            $service,
            TypeEIEnum::Chute,
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('-2 days'),
            deces: true,
            pronosticVitalEnJeu: false,
            risqueDeficitFonctionnelPermanent: false,
        );

        self::assertSame(StatutEnum::Brouillon, $declaration->getStatut());
        self::assertSame(GraviteEnum::Deces, $declaration->getGravite());
        self::assertTrue($declaration->isEIGS());
        self::assertSame($declarant, $declaration->getDeclarant());
        self::assertSame($service, $declaration->getService());
    }

    public function testCreateDraftAssignsReference(): void
    {
        // La référence lisible (DCL-2026-0042) doit exister dès le brouillon :
        // un soignant peut en parler à son cadre avant de soumettre.
        $service = $this->createService();
        $declarant = $this->createSoignant($service);
        $manager = $this->createManager();

        $declaration = $manager->createDraft(
            $declarant,
            $service,
            TypeEIEnum::Chute,
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('-2 days'),
            deces: false,
            pronosticVitalEnJeu: false,
            risqueDeficitFonctionnelPermanent: false,
            choixSiNonEIGS: GraviteEnum::Mineur,
        );

        self::assertSame('DCL-2026-0001', $declaration->getReference());
    }

    public function testCreateDraftThrowsIfDateConstatIsInFuture(): void
    {
        $service = $this->createService();
        $declarant = $this->createSoignant($service);
        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->createDraft(
            $declarant,
            $service,
            TypeEIEnum::Chute,
            new \DateTimeImmutable('+1 day'),
            new \DateTimeImmutable(),
            deces: false,
            pronosticVitalEnJeu: false,
            risqueDeficitFonctionnelPermanent: false,
            choixSiNonEIGS: GraviteEnum::Mineur,
        );
    }

    public function testCreateDraftThrowsIfServiceNotAmongDeclarantsServices(): void
    {
        $serviceAutorise = $this->createService();
        $declarant = $this->createSoignant($serviceAutorise);
        $autreService = $this->createService();

        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->createDraft(
            $declarant,
            $autreService,
            TypeEIEnum::Chute,
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('-1 day'),
            deces: false,
            pronosticVitalEnJeu: false,
            risqueDeficitFonctionnelPermanent: false,
            choixSiNonEIGS: GraviteEnum::Mineur,
        );
    }

    // --- updateDraft() ---

    public function testUpdateDraftAppliesAllowedFieldsWhenBrouillon(): void
    {
        $declaration = new Declaration();
        // statut = Brouillon par défaut (constructeur)

        $manager = $this->createManager();

        $updated = $manager->updateDraft($declaration, [
            'description' => 'Chute dans le couloir du service',
        ]);

        self::assertSame('Chute dans le couloir du service', $updated->getDescription());
    }

    public function testUpdateDraftThrowsWhenNotModifiable(): void
    {
        $declaration = new Declaration();
        $declaration->setStatut(StatutEnum::Soumise);

        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->updateDraft($declaration, ['description' => 'Tentative de modification']);
    }

    public function testUpdateDraftThrowsWhenDescriptionTooShort(): void
    {
        // CDC §4.3 : minimum 20 caractères.
        $declaration = new Declaration();
        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->updateDraft($declaration, ['description' => 'Trop court']);
    }

    public function testUpdateDraftThrowsWhenDateConstatIsInFuture(): void
    {
        $declaration = new Declaration();
        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->updateDraft($declaration, ['dateConstat' => new \DateTimeImmutable('+1 day')]);
    }

    public function testUpdateDraftThrowsWhenLieuDifferentWithoutDetail(): void
    {
        // CDC §4.3 : champ texte conditionnel si "lieu différent" = Oui.
        $declaration = new Declaration();
        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->updateDraft($declaration, ['lieuDifferent' => true]);
    }

    public function testUpdateDraftAllowsLieuDifferentWithDetail(): void
    {
        $declaration = new Declaration();
        $manager = $this->createManager();

        $updated = $manager->updateDraft($declaration, [
            'lieuDifferent' => true,
            'lieuDifferentDetail' => 'Constaté dans la salle d\'attente plutôt qu\'en chambre',
        ]);

        self::assertTrue($updated->isLieuDifferent());
    }

    public function testUpdateDraftThrowsWhenConsequencesAutresWithoutDetail(): void
    {
        $declaration = new Declaration();
        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->updateDraft($declaration, ['consequencesAutres' => true]);
    }

    public function testUpdateDraftThrowsWhenMesuresImmediatesPatientWithoutDetail(): void
    {
        $declaration = new Declaration();
        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->updateDraft($declaration, ['mesuresImmediatesPatient' => true]);
    }

    // --- abandon() ---

    public function testAbandonTransitionsBrouillonToAbandonnee(): void
    {
        $declaration = new Declaration();

        $manager = $this->createManager();
        $manager->abandon($declaration);

        self::assertSame(StatutEnum::Abandonnee, $declaration->getStatut());
    }

    public function testAbandonThrowsWhenTransitionNotAllowed(): void
    {
        $declaration = new Declaration();
        $declaration->setStatut(StatutEnum::Soumise);

        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->abandon($declaration);
    }

    // --- submit() ---

    public function testSubmitTransitionsNotifiesAndLogs(): void
    {
        $service = $this->createService();
        $declarant = $this->createSoignant($service);

        $declaration = new Declaration();
        $declaration->setDeclarant($declarant);
        $declaration->setService($service);

        $notificationManager = $this->createMock(NotificationManager::class);
        $notificationManager->expects($this->once())->method('notifySubmission')->with($declaration);

        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->once())
            ->method('log')
            ->with(LogTypeEnum::DeclarationSubmitted, $declarant, $this->isString());

        $manager = $this->createManager(notificationManager: $notificationManager, logManager: $logManager);

        $manager->submit($declaration);

        self::assertSame(StatutEnum::Soumise, $declaration->getStatut());
    }

    public function testSubmitThrowsWhenTransitionNotAllowed(): void
    {
        $declaration = new Declaration();
        $declaration->setStatut(StatutEnum::Abandonnee);

        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->submit($declaration);
    }

    // --- changeStatut() ---

    public function testChangeStatutAppliesAllowedTransition(): void
    {
        $declaration = new Declaration();
        $declaration->setStatut(StatutEnum::Soumise);

        $manager = $this->createManager();
        $manager->changeStatut($declaration, StatutEnum::EnAnalyse);

        self::assertSame(StatutEnum::EnAnalyse, $declaration->getStatut());
    }

    public function testChangeStatutThrowsWhenTransitionNotAllowed(): void
    {
        $declaration = new Declaration();
        // Brouillon -> EnAnalyse n'est pas une transition autorisée
        // (seul submit() peut faire Brouillon -> Soumise -> ... -> EnAnalyse).

        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->changeStatut($declaration, StatutEnum::EnAnalyse);
    }

    // --- resolvePerimeterCriteria() ---

    public function testResolvePerimeterCriteriaForChefPoleReturnsPole(): void
    {
        $service = $this->createService();
        $chefPole = new User();
        $chefPole->setEmail('chef@test.fr');
        $chefPole->setRole(RoleEnum::ChefPole);
        $chefPole->setPassword('hash');
        $chefPole->addService($service);

        $manager = $this->createManager();

        $criteria = $manager->resolvePerimeterCriteria($chefPole);

        self::assertSame($service->getPole(), $criteria['pole']);
    }

    public function testResolvePerimeterCriteriaForCadreReturnsServices(): void
    {
        $service = $this->createService();
        $cadre = new User();
        $cadre->setEmail('cadre@test.fr');
        $cadre->setRole(RoleEnum::Cadre);
        $cadre->setPassword('hash');
        $cadre->addService($service);

        $manager = $this->createManager();

        $criteria = $manager->resolvePerimeterCriteria($cadre);

        self::assertSame([$service], $criteria['services']);
    }

    public function testResolvePerimeterCriteriaForSoignantReturnsDeclarant(): void
    {
        $service = $this->createService();
        $soignant = $this->createSoignant($service);

        $manager = $this->createManager();

        $criteria = $manager->resolvePerimeterCriteria($soignant);

        self::assertSame($soignant, $criteria['declarant']);
    }

    public function testResolvePerimeterCriteriaThrowsForAdmin(): void
    {
        $admin = new User();
        $admin->setEmail('admin@test.fr');
        $admin->setRole(RoleEnum::Admin);
        $admin->setPassword('hash');

        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);

        $manager->resolvePerimeterCriteria($admin);
    }

    // --- search() ---

    public function testSearchDelegatesToRepositoryWithResolvedCriteria(): void
    {
        $service = $this->createService();
        $soignant = $this->createSoignant($service);
        $filters = ['motCle' => 'chute'];
        $resultatAttendu = [new Declaration()];

        $declarationRepository = $this->createMock(DeclarationRepository::class);
        $declarationRepository->expects($this->once())
            ->method('search')
            ->with(['declarant' => $soignant], $filters)
            ->willReturn($resultatAttendu);

        $manager = $this->createManager(declarationRepository: $declarationRepository);

        $result = $manager->search($soignant, $filters);

        self::assertSame($resultatAttendu, $result);
    }
}
