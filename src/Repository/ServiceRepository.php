<?php

namespace App\Repository;

use App\Entity\Pole;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findByPole(Pole $pole): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.pole = :pole')
            ->setParameter('pole', $pole)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    public function findByName(string $nom): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.nom = :nom')
            ->setParameter('nom', $nom)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
    //    /**
    //     * @return Service[] Returns an array of Service objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Service
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

}
