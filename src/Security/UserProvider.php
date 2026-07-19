<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Charge un utilisateur depuis la base de données par email.
 *
 * @implements UserProviderInterface<User>
 */
class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepository $userRepository) {}

    /**
     * Charge l'utilisateur par son email (getUserIdentifier()).
     * Vérifie également que le compte est actif (CDC §3 — isActive).
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);

        if (!$user) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        if (!$user->isActive()) {
            // Compte désactivé — traité comme inexistant (message générique côté client)
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $user;
    }

    /**
     * Rafraîchit l'utilisateur entre deux requêtes (sessions ou token refresh).
     * Pour une API stateless, cette méthode est appelée à chaque requête.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }
}
