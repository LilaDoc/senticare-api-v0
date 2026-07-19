<?php

namespace App\Service;

use App\Entity\Pole;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Création et gestion des comptes utilisateurs (CDC UC-10, UC-12, US-1.2, US-1.3).
 *
 * Le contrôle de périmètre (qui a le droit de créer/désactiver qui) est fait
 * par UserVoter, en amont, dans le contrôleur — ce service applique la logique
 * métier une fois l'autorisation acquise.
 */
class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly NotificationManager $notificationManager,
    ) {
    }

    /**
     * Crée un compte soignant/cadre (par un chef de pôle) ou chef de pôle (par un admin).
     * Génère un mot de passe provisoire et l'envoie par email (US-1.2).
     *
     * @param array<int, \App\Entity\Service> $services
     */
    public function create(
        string $email,
        string $nom,
        string $prenom,
        RoleEnum $role,
        User $createdBy,
        array $services = [],
    ): User {
        // TODO:
        // 1. vérifier l'unicité de l'email (UserRepository::findOneBy(['email' => ...]))
        // 2. générer un mot de passe provisoire aléatoire (ex: random_bytes + base64)
        // 3. hasher via $this->passwordHasher->hashPassword() — Argon2id (CDC §6.1)
        // 4. instancier User, setCreatedBy($createdBy), rattacher $services
        // 5. persister + flush
        // 6. envoyer le mot de passe provisoire via $this->notificationManager (US-1.2)
        throw new \RuntimeException('TODO: implement UserManager::create()');
    }

    /**
     * Met à jour un compte existant (nom/prénom/services). Ne touche jamais à
     * l'email (identifiant de connexion) ni au rôle — ce sont des opérations
     * sensibles distinctes, hors périmètre de cette méthode.
     *
     * @param array<int, \App\Entity\Service> $services
     */
    public function update(User $user, string $nom, string $prenom, array $services): User
    {
        // TODO: setNom/setPrenom, remplacer les services (removeService sur les
        // anciens absents de $services, addService sur les nouveaux), flush.
        throw new \RuntimeException('TODO: implement UserManager::update()');
    }

    /**
     * Désactive un compte sans le supprimer — traçabilité conservée (CDC §5.1).
     */
    public function deactivate(User $user): void
    {
        // TODO: $user->setIsActive(false); flush(); journaliser l'action (CDC §6.3).
        throw new \RuntimeException('TODO: implement UserManager::deactivate()');
    }

    /**
     * Réactive un compte désactivé — symétrique de deactivate() (CDC §5.1 :
     * une désactivation "sans suppression" doit pouvoir être annulée).
     */
    public function reactivate(User $user): void
    {
        // TODO: $user->setIsActive(true); flush(); journaliser l'action (CDC §6.3).
        throw new \RuntimeException('TODO: implement UserManager::reactivate()');
    }

    /**
     * Comptes soignants/cadres rattachés à un service du pôle donné (périmètre chef de pôle).
     *
     * @return User[]
     */
    public function findByPole(Pole $pole): array
    {
        // TODO: requête via UserRepository, jointure services -> pole (User n'a pas de FK directe vers Pole).
        throw new \RuntimeException('TODO: implement UserManager::findByPole()');
    }

    /**
     * Comptes chefs de pôle (périmètre admin, CDC §3 — l'admin ne voit pas soignants/cadres).
     *
     * @return User[]
     */
    public function findChefsPole(): array
    {
        // TODO: requête filtrée sur roles contient ROLE_CHEF_POLE.
        throw new \RuntimeException('TODO: implement UserManager::findChefsPole()');
    }
}
