<?php

namespace App\Repository;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
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
     * Cadres rattachés à ce service (peut y en avoir plusieurs) — utilisé pour
     * la notification de soumission (UC-09).
     *
     * @return User[]
     */
    public function findCadresByService(Service $service): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.services', 's')
            ->andWhere('s = :service')
            ->andWhere('u.role = :role')
            ->setParameter('service', $service)
            ->setParameter('role', RoleEnum::Cadre)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Comptes ayant le rôle donné.
     *
     * @return User[]
     */
    public function findByRole(RoleEnum $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.role = :role')
            ->setParameter('role', $role)
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
