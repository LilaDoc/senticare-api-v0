<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Security\Voter\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * CDC §3, UC-10, UC-12 : le chef de pôle gère les comptes soignants/cadres de son
 * pôle ; l'admin gère les comptes chefs de pôle et peut désactiver n'importe quel compte.
 *
 * Tests unitaires purs (pas de kernel/DB). Ils échouent tant que UserVoter
 * lève RuntimeException('TODO...') — implémente jusqu'à ce qu'ils passent.
 */
class UserVoterTest extends TestCase
{
    private UserVoter $voter;
    private Pole $pole;
    private Service $service;

    protected function setUp(): void
    {
        $this->voter = new UserVoter();

        $this->pole = new Pole();
        $this->pole->setNom('Chirurgie');

        $this->service = new Service();
        $this->service->setNom('Bloc opératoire');
        $this->service->setPole($this->pole);
    }

    private function buildUser(RoleEnum $role, array $services = []): User
    {
        $user = new User();
        $user->setEmail(strtolower($role->name).'@test.fr');
        $user->setNom('Nom');
        $user->setPrenom('Prenom');
        $user->setRoles([$role->value]);
        $user->setPassword('irrelevant-hash');

        foreach ($services as $service) {
            $user->addService($service);
        }

        return $user;
    }

    private function vote(User $currentUser, ?User $subject, string $attribute): int
    {
        $token = new UsernamePasswordToken($currentUser, 'main', $currentUser->getRoles());

        return $this->voter->vote($token, $subject, [$attribute]);
    }

    public function testChefPoleCanCreateAccount(): void
    {
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($chefPole, null, UserVoter::CREATE));
    }

    public function testAdminCanCreateAccount(): void
    {
        $admin = $this->buildUser(RoleEnum::Admin);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, null, UserVoter::CREATE));
    }

    public function testSoignantCannotCreateAccount(): void
    {
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($soignant, null, UserVoter::CREATE));
    }

    public function testChefPoleCanDeactivateUserOfHisPole(): void
    {
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($chefPole, $soignant, UserVoter::DEACTIVATE));
    }

    public function testChefPoleCannotDeactivateUserOfAnotherPole(): void
    {
        $autrePole = new Pole();
        $autrePole->setNom('Oncologie');
        $autreService = new Service();
        $autreService->setNom('Hôpital de jour');
        $autreService->setPole($autrePole);

        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$autreService]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($chefPole, $soignant, UserVoter::DEACTIVATE));
    }

    public function testAdminCanDeactivateAnyAccount(): void
    {
        $admin = $this->buildUser(RoleEnum::Admin);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $soignant, UserVoter::DEACTIVATE));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $chefPole, UserVoter::DEACTIVATE));
    }

    public function testAdminCanOnlyViewChefPoleAccounts(): void
    {
        // CDC §3 : l'admin gère les comptes chefs de pôle, pas les soignants/cadres.
        $admin = $this->buildUser(RoleEnum::Admin);
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $chefPole, UserVoter::VIEW));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($admin, $soignant, UserVoter::VIEW));
    }

    public function testChefPoleCanEditUserOfHisPole(): void
    {
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($chefPole, $soignant, UserVoter::EDIT));
    }

    public function testChefPoleCannotEditUserOfAnotherPole(): void
    {
        $autrePole = new Pole();
        $autrePole->setNom('Oncologie');
        $autreService = new Service();
        $autreService->setNom('Hôpital de jour');
        $autreService->setPole($autrePole);

        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$autreService]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($chefPole, $soignant, UserVoter::EDIT));
    }

    public function testAdminCanReactivateAnyAccount(): void
    {
        // CDC §5.1 : une désactivation "sans suppression" doit être réversible.
        $admin = $this->buildUser(RoleEnum::Admin);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $soignant, UserVoter::REACTIVATE));
    }

    public function testChefPoleCannotReactivateUserOfAnotherPole(): void
    {
        $autrePole = new Pole();
        $autrePole->setNom('Oncologie');
        $autreService = new Service();
        $autreService->setNom('Hôpital de jour');
        $autreService->setPole($autrePole);

        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);
        $soignant = $this->buildUser(RoleEnum::Soignant, [$autreService]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($chefPole, $soignant, UserVoter::REACTIVATE));
    }
}
