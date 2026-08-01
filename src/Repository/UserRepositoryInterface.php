<?php

namespace App\Repository;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;

/**
 * Contrat minimal utilisé par UserManager/NotificationManager — permet de
 * les tester unitairement avec un Fake en mémoire (InMemoryUserRepository)
 * plutôt qu'une vraie base (cf. docs/REVISION_ORAL.md, niveau 1 vs 2).
 */
interface UserRepositoryInterface
{
    public function findOneByEmail(string $email): ?User;

    /** @return User[] */
    public function findByPole(Pole $pole): array;

    /** @return User[] */
    public function findByRole(RoleEnum $role): array;

    /** @return User[] */
    public function findCadresByService(Service $service): array;
}
