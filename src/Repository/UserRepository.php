<?php

namespace App\Repository;

use App\Entity\Pole;
use App\Entity\User;
use App\Enum\RoleEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Comptes rattachés à au moins un service du pôle donné (périmètre chef de pôle).
     *
     * @return User[]
     */
    public function findByPole(Pole $pole): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.services', 's')
            ->andWhere('s.pole = :pole')
            ->setParameter('pole', $pole)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Comptes ayant le rôle donné. Les rôles sont stockés en JSON (ex:
     * ["ROLE_CHEF_POLE"]) — pas d'opérateur JSON natif portable en DQL, on
     * teste donc la présence de la valeur sérialisée dans la colonne texte.
     *
     * @return User[]
     */
    public function findByRole(RoleEnum $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role->value.'"%')
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
