<?php

namespace App\Repository;

use App\Entity\Declaration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Declaration>
 */
class DeclarationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Declaration::class);
    }

    /**
     * Construit et exécute la requête pour le tableau de bord superviseur
     * (UC-06, CDC §4.6). Le périmètre (qui a le droit de voir quoi) est déjà
     * résolu par DeclarationManager::search() avant l'appel — ce repository ne
     * fait que traduire $criteria/$filters en requête, aucune règle métier ici.
     *
     * @param array{declarant?: \App\Entity\User, services?: array, pole?: ?\App\Entity\Pole} $criteria
     * @param array{statut?: \App\Enum\StatutEnum, dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable, typeEI?: \App\Enum\TypeEIEnum, gravite?: \App\Enum\GraviteEnum, eigsOnly?: bool, motCle?: string} $filters
     *
     * @return Declaration[]
     */
    public function search(array $criteria, array $filters): array
    {
        $qb = $this->createQueryBuilder('d');

        if (isset($criteria['declarant'])) {
            $qb->andWhere('d.declarant = :declarant')->setParameter('declarant', $criteria['declarant']);
        }
        if (isset($criteria['services'])) {
            $qb->andWhere('d.service IN (:services)')->setParameter('services', $criteria['services']);
        }
        if (array_key_exists('pole', $criteria)) {
            $qb->innerJoin('d.service', 's')
                ->andWhere('s.pole = :pole')
                ->setParameter('pole', $criteria['pole']);
        }

        if (isset($filters['statut'])) {
            $qb->andWhere('d.statut = :statut')->setParameter('statut', $filters['statut']);
        }
        if (isset($filters['dateFrom'])) {
            $qb->andWhere('d.dateConstat >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom']);
        }
        if (isset($filters['dateTo'])) {
            $qb->andWhere('d.dateConstat <= :dateTo')->setParameter('dateTo', $filters['dateTo']);
        }
        if (isset($filters['typeEI'])) {
            $qb->andWhere('d.typeEI = :typeEI')->setParameter('typeEI', $filters['typeEI']);
        }
        if (isset($filters['gravite'])) {
            $qb->andWhere('d.gravite = :gravite')->setParameter('gravite', $filters['gravite']);
        }
        if (!empty($filters['eigsOnly'])) {
            $qb->andWhere('d.isEIGS = true');
        }
        if (!empty($filters['motCle'])) {
            $qb->andWhere('d.description LIKE :motCle')->setParameter('motCle', '%'.$filters['motCle'].'%');
        }

        return $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }
    public function findOneById($value): ?Declaration
    {
        return $this->createQueryBuilder('d')
           ->andWhere('d.id = :val')
           ->setParameter('val', $value)
           ->getQuery()
           ->getOneOrNullResult()
        ;
    }
    //    /**
    //     * @return Declaration[] Returns an array of Declaration objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Declaration
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
