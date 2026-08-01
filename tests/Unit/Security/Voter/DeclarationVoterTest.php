<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Declaration;
use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\RoleEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Security\Voter\DeclarationVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * CDC §3 : soignant = ses déclarations, cadre = son/ses service(s), chef de pôle =
 * son pôle, admin = aucun accès (interdiction explicite, pas une simple absence de droit).
 *
 * Test unitaire pur : pas de kernel/DB, les entités sont assemblées à la main.
 * Ces tests appellent réellement DeclarationVoter — tant que les méthodes
 * privées lèvent RuntimeException('TODO...'), ils échouent (rouge), c'est
 * attendu en TDD. Implémente le Voter jusqu'à ce qu'ils passent (vert).
 */
class DeclarationVoterTest extends TestCase
{
    private DeclarationVoter $voter;
    private Pole $pole;
    private Service $service;
    private Service $autreService;

    protected function setUp(): void
    {
        $this->voter = new DeclarationVoter();

        $this->pole = new Pole();
        $this->pole->setNom('Chirurgie');

        $this->service = new Service();
        $this->service->setNom('Bloc opératoire');
        $this->service->setPole($this->pole);

        $this->autreService = new Service();
        $this->autreService->setNom('Urgences');
        $this->autreService->setPole(new Pole());
    }

    private function buildUser(RoleEnum $role, array $services = []): User
    {
        $user = new User();
        $user->setEmail(strtolower($role->name).'@test.fr');
        $user->setNom('Nom');
        $user->setPrenom('Prenom');
        $user->setRole($role);
        $user->setPassword('irrelevant-hash');

        foreach ($services as $service) {
            $user->addService($service);
        }

        return $user;
    }

    private function buildDeclaration(User $declarant, Service $service, StatutEnum $statut = StatutEnum::Brouillon): Declaration
    {
        $declaration = new Declaration();
        $declaration->setDeclarant($declarant);
        $declaration->setService($service);
        $declaration->setTypeEI(TypeEIEnum::Chute);
        $declaration->setDateConstat(new \DateTimeImmutable());
        $declaration->setDateSurvenue(new \DateTimeImmutable());
        $declaration->setStatut($statut);

        return $declaration;
    }

    private function vote(User $user, Declaration $declaration, string $attribute): int
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        return $this->voter->vote($token, $declaration, [$attribute]);
    }

    public function testSoignantCanViewOwnDeclaration(): void
    {
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $declaration = $this->buildDeclaration($soignant, $this->service);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($soignant, $declaration, DeclarationVoter::VIEW));
    }

    public function testSoignantCannotViewAnotherSoignantDeclaration(): void
    {
        $auteur = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $autreSoignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $declaration = $this->buildDeclaration($auteur, $this->service);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($autreSoignant, $declaration, DeclarationVoter::VIEW));
    }

    public function testCadreCanViewDeclarationOfOwnService(): void
    {
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $cadre = $this->buildUser(RoleEnum::Cadre, [$this->service]);
        $declaration = $this->buildDeclaration($soignant, $this->service);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($cadre, $declaration, DeclarationVoter::VIEW));
    }

    public function testCadreCannotViewDeclarationOfAnotherService(): void
    {
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->autreService]);
        $cadre = $this->buildUser(RoleEnum::Cadre, [$this->service]);
        $declaration = $this->buildDeclaration($soignant, $this->autreService);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($cadre, $declaration, DeclarationVoter::VIEW));
    }

    public function testChefPoleCanViewDeclarationOfAnyServiceInHisPole(): void
    {
        $autreServiceMemePole = new Service();
        $autreServiceMemePole->setNom('Chirurgie ambulatoire');
        $autreServiceMemePole->setPole($this->pole);

        $soignant = $this->buildUser(RoleEnum::Soignant, [$autreServiceMemePole]);
        // Convention (cf. AppFixtures) : le chef de pôle est rattaché à tous les
        // services de son pôle, faute de relation directe User -> Pole.
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service, $autreServiceMemePole]);
        $declaration = $this->buildDeclaration($soignant, $autreServiceMemePole);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($chefPole, $declaration, DeclarationVoter::VIEW));
    }

    public function testAdminNeverHasAccessToDeclarations(): void
    {
        // CDC §3 : "N'a pas accès aux déclarations d'EI" — interdiction explicite,
        // même sur une déclaration dans un service qu'il aurait pu créer.
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $admin = $this->buildUser(RoleEnum::Admin);
        $declaration = $this->buildDeclaration($soignant, $this->service);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($admin, $declaration, DeclarationVoter::VIEW));
    }

    public function testOnlyDeclarantCanEditDraft(): void
    {
        $declarant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $cadre = $this->buildUser(RoleEnum::Cadre, [$this->service]);
        $declaration = $this->buildDeclaration($declarant, $this->service, StatutEnum::Brouillon);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($declarant, $declaration, DeclarationVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($cadre, $declaration, DeclarationVoter::EDIT));
    }

    public function testDeclarantCannotEditSubmittedDeclaration(): void
    {
        // CDC §4.4 : seul le brouillon est modifiable par le déclarant.
        $declarant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $declaration = $this->buildDeclaration($declarant, $this->service, StatutEnum::Soumise);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($declarant, $declaration, DeclarationVoter::EDIT));
    }

    public function testOnlyCadreOrChefPoleCanChangeStatut(): void
    {
        $declarant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $cadre = $this->buildUser(RoleEnum::Cadre, [$this->service]);
        $declaration = $this->buildDeclaration($declarant, $this->service, StatutEnum::Soumise);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($cadre, $declaration, DeclarationVoter::CHANGE_STATUT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($declarant, $declaration, DeclarationVoter::CHANGE_STATUT));
    }
}
