<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Declaration;
use App\Entity\Pole;
use App\Entity\Service;
use App\Enum\RoleEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadUsers($manager);
        $this->loadServices($manager);

        $manager->flush();
    }

    private function loadUsers(ObjectManager $manager): void
    {
        $users = [
            [
                'email' => 'soignant@senticare.fr',
                'role' => RoleEnum::Soignant,
                'nom' => 'Dupont',
                'prenom' => 'Marie',
                'isActive' => true,
                'lastLoginAt' => new \DateTimeImmutable('2026-07-10 14:30:00'),
                'reference' => 'user_soignant',
            ],
            [
                'email' => 'chefpole@senticare.fr',
                'role' => RoleEnum::ChefPole,
                'nom' => 'Martin',
                'prenom' => 'Paul',
                'isActive' => true,
                'lastLoginAt' => new \DateTimeImmutable('2026-07-10 14:30:00'),
                'reference' => 'user_chefpole',
            ],
            [
                'email' => 'admin@senticare.fr',
                'role' => RoleEnum::Admin,
                'nom' => 'Admin',
                'prenom' => 'System',
                'isActive' => true,
                'lastLoginAt' => new \DateTimeImmutable('2026-07-10 14:30:00'),
                'reference' => 'user_admin',
            ],
            [
                'email' => 'cadre@senticare.fr',
                'role' => RoleEnum::Cadre,
                'nom' => 'Bernard',
                'prenom' => 'Sophie',
                'isActive' => true,
                'lastLoginAt' => new \DateTimeImmutable('2026-07-10 14:30:00'),
                'reference' => 'user_cadre',
            ],
        ];

        foreach ($users as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setRole($data['role']);
            $user->setNom($data['nom']);
            $user->setPrenom($data['prenom']);
            $user->setIsActive($data['isActive']);
            $user->setLastLoginAt($data['lastLoginAt']);
            // Mot de passe de démo — haché Argon2id, jamais stocké en clair (CDC §6.1).
            $user->setPassword($this->passwordHasher->hashPassword($user, 'Senticare2026!'));

            $manager->persist($user);
            $this->addReference($data['reference'], $user);
        }
    }

    private function loadServices(ObjectManager $manager): void
    {
        // Créé par le chef de pôle, conformément au commentaire de l'entity User
        // (Chef de pôle pour soignants/cadres, Admin pour chefs de pôle)
        $createdBy = $this->getReference('user_chefpole', User::class);

        $services = [
            [
                'nom' => 'Urgences',
                'reference' => 'service_urgences',
            ],
            [
                'nom' => 'Gériatrie',
                'reference' => 'service_geriatrie',
            ],
            [
                'nom' => 'Oncologie',
                'reference' => 'service_oncologie',
            ],
        ];

        foreach ($services as $data) {
            $service = new Service();
            $service->setNom($data['nom']);
            $service->setCreatedBy($createdBy);

            // Rattachement du soignant test à chaque service
            $service->addUser($this->getReference('user_soignant', User::class));

            // Le chef de pôle est rattaché aux 3 services
            $service->addUser($this->getReference('user_chefpole', User::class));

            $manager->persist($service);
            $this->addReference($data['reference'], $service);
        }

        // Le cadre est rattaché uniquement au service Urgences
        $this->getReference('service_urgences', Service::class)
            ->addUser($this->getReference('user_cadre', User::class));
    }
}