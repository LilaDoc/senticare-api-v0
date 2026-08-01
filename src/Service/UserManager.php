<?php

namespace App\Service;

use App\Entity\Pole;
use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Enum\RoleEnum;
use App\Repository\UserRepositoryInterface;
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
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly NotificationManager $notificationManager,
        private readonly LogManager $logManager,
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
        if ($this->userRepository->findOneByEmail($email)) {
            throw new \RuntimeException('Un compte avec cet email existe déjà.');
        }

        $newUser = new User();
        $newUser->setEmail($email);
        $newUser->setNom($nom);
        $newUser->setPrenom($prenom);
        $newUser->setRole($role);
        $newUser->setCreatedBy($createdBy);

        foreach ($services as $service) {
            $newUser->addService($service);
        }

        $plainPassword = $this->createUserWithGeneratedPassword($newUser);

        $this->entityManager->persist($newUser);
        $this->entityManager->flush();

        $this->notificationManager->notifyAccountCreated($newUser, $plainPassword);

        $this->logManager->log(
            LogTypeEnum::UserCreated,
            $createdBy,
            sprintf('Compte %s créé (rôle %s) par %s', $newUser->getEmail(), $role->label(), $createdBy->getEmail())
        );

        return $newUser;
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
        $user->setNom($nom);
        $user->setPrenom($prenom);

        foreach ($user->getServices() as $existingService) {
            if (!in_array($existingService, $services, true)) {
                $user->removeService($existingService);
            }
        }

        foreach ($services as $newService) {
            if (!$user->getServices()->contains($newService)) {
                $user->addService($newService);
            }
        }

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Change son propre mot de passe (US-1.2 — l'utilisateur doit pouvoir
     * remplacer le mot de passe provisoire reçu à la création). Vérifie
     * l'ancien mot de passe avant d'appliquer le nouveau, hashé en Argon2id.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new \RuntimeException('Mot de passe actuel incorrect.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

        $this->entityManager->flush();
    }

    /**
     * Désactive un compte sans le supprimer — traçabilité conservée (CDC §5.1).
     */
    public function deactivate(User $user, User $actor): void
    {
        $user->setIsActive(false);

        $this->entityManager->flush();

        $this->logManager->log(LogTypeEnum::UserDeactivated, $actor, sprintf('Compte %s désactivé par %s', $user->getEmail(), $actor->getEmail()));
    }

    /**
     * Réactive un compte désactivé — symétrique de deactivate() (CDC §5.1 :
     * une désactivation "sans suppression" doit pouvoir être annulée).
     */
    public function reactivate(User $user, User $actor): void
    {
        $user->setIsActive(true);

        $this->entityManager->flush();

        $this->logManager->log(LogTypeEnum::UserReactivated, $actor, sprintf('Compte %s réactivé par %s', $user->getEmail(), $actor->getEmail()));
    }

    /**
     * Comptes soignants/cadres rattachés à un service du pôle donné (périmètre chef de pôle).
     *
     * @return User[]
     */
    public function findByPole(Pole $pole): array
    {
        return $this->userRepository->findByPole($pole);
    }

    /**
     * Comptes chefs de pôle (périmètre admin, CDC §3 — l'admin ne voit pas soignants/cadres).
     *
     * @return User[]
     */
    public function findChefsPole(): array
    {
        return $this->userRepository->findByRole(RoleEnum::ChefPole);
    }
    
    private function generateRandomPassword(int $length = 12): string
        {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // sans I/O pour éviter confusion
        $lowercase = 'abcdefghijkmnpqrstuvwxyz';
        $numbers = '23456789'; // sans 0/1
        $special = '!@#$%^&*-_';

        $all = $uppercase . $lowercase . $numbers . $special;

        // on garantit au moins un caractère de chaque catégorie
        $password = [
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $special[random_int(0, strlen($special) - 1)],
        ];

        for ($i = count($password); $i < $length; $i++) {
            $password[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }

    public function createUserWithGeneratedPassword(User $user): string
    {
        $plainPassword = $this->generateRandomPassword();
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        return $plainPassword;
    }

}
