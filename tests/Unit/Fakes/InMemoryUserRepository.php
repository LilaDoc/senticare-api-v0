<?php

namespace App\Tests\Unit\Fakes;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Repository\UserRepositoryInterface;

/**
 * FAKE (cf. 01_tests-2.md) : implémentation en mémoire de
 * UserRepositoryInterface, comme CommandeRepositoryEnMemoire dans le cours
 * — se comporte "pour de vrai" (on ajoute puis on relit), sans jamais
 * toucher une vraie base de données.
 */
class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var User[] */
    private array $users = [];

    public function add(User $user): void
    {
        $this->users[] = $user;
    }

    public function findOneByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findByPole(Pole $pole): array
    {
        return array_values(array_filter($this->users, function (User $user) use ($pole): bool {
            foreach ($user->getServices() as $service) {
                if ($service->getPole() === $pole) {
                    return true;
                }
            }

            return false;
        }));
    }

    public function findByRole(RoleEnum $role): array
    {
        return array_values(array_filter(
            $this->users,
            fn (User $user): bool => in_array($role->value, $user->getRoles(), true)
        ));
    }

    public function findCadresByService(Service $service): array
    {
        return array_values(array_filter($this->users, function (User $user) use ($service): bool {
            return in_array(RoleEnum::Cadre->value, $user->getRoles(), true)
                && $user->getServices()->contains($service);
        }));
    }
}
