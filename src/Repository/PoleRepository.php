<?php

namespace App\Repository;

use App\Entity\Pole;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pole>
 */
class PoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pole::class);
    }


    /**
     * Pôles auxquels un utilisateur est rattaché, via ses services (User n'a
     * pas de relation directe vers Pole — cf. convention documentée dans
     * docs/ROADMAP.md : un chef de pôle est rattaché à tous les services de
     * son pôle).
     *
     * @return Pole[]
     */

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->distinct()
            ->innerJoin('p.services', 's')
            ->innerJoin('s.users', 'u')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult()
        ;
    }
}
