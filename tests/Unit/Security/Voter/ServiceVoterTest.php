<?php

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Security\Voter\ServiceVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * CDC UC-13 : l'admin gère tous les services, le chef de pôle uniquement ceux
 * de son propre pôle.
 *
 * Tests unitaires purs (pas de kernel/DB). Ils échouent tant que ServiceVoter
 * lève RuntimeException('TODO...') — implémente jusqu'à ce qu'ils passent.
 */
class ServiceVoterTest extends TestCase
{
    private ServiceVoter $voter;
    private Pole $pole;
    private Service $service;

    protected function setUp(): void
    {
        $this->voter = new ServiceVoter();

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
        $user->setRole($role);
        $user->setPassword('irrelevant-hash');

        foreach ($services as $service) {
            $user->addService($service);
        }

        return $user;
    }

    private function vote(User $currentUser, mixed $subject, string $attribute): int
    {
        $token = new UsernamePasswordToken($currentUser, 'main', $currentUser->getRoles());

        return $this->voter->vote($token, $subject, [$attribute]);
    }

    public function testAdminCanCreateServiceInAnyPole(): void
    {
        $admin = $this->buildUser(RoleEnum::Admin);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $this->pole, ServiceVoter::CREATE));
    }

    public function testChefPoleCanCreateServiceInHisOwnPole(): void
    {
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($chefPole, $this->pole, ServiceVoter::CREATE));
    }

    public function testChefPoleCannotCreateServiceInAnotherPole(): void
    {
        $autrePole = new Pole();
        $autrePole->setNom('Oncologie');

        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($chefPole, $autrePole, ServiceVoter::CREATE));
    }

    public function testSoignantCannotCreateService(): void
    {
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($soignant, $this->pole, ServiceVoter::CREATE));
    }

    public function testAdminCanManageAnyService(): void
    {
        $admin = $this->buildUser(RoleEnum::Admin);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $this->service, ServiceVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($admin, $this->service, ServiceVoter::DEACTIVATE));
    }

    public function testChefPoleCanManageServiceOfHisOwnPole(): void
    {
        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($chefPole, $this->service, ServiceVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($chefPole, $this->service, ServiceVoter::DEACTIVATE));
    }

    public function testChefPoleCannotManageServiceOfAnotherPole(): void
    {
        $autrePole = new Pole();
        $autrePole->setNom('Oncologie');
        $autreService = new Service();
        $autreService->setNom('Hôpital de jour');
        $autreService->setPole($autrePole);

        $chefPole = $this->buildUser(RoleEnum::ChefPole, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($chefPole, $autreService, ServiceVoter::EDIT));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($chefPole, $autreService, ServiceVoter::DEACTIVATE));
    }

    public function testSoignantCannotManageService(): void
    {
        $soignant = $this->buildUser(RoleEnum::Soignant, [$this->service]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($soignant, $this->service, ServiceVoter::EDIT));
    }
}
