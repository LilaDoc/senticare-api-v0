<?php

namespace App\Repository;

use App\Entity\Declaration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        $qb = $this->applyPerimeterAndFilters($this->createQueryBuilder('d'), $criteria, $filters);

        return $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }

    /**
     * Statistiques agrégées pour le tableau de bord superviseur (UC-06, CDC
     * §4.6). Mêmes $criteria/$filters que search() — même règle : le
     * périmètre est déjà résolu par StatsManager avant l'appel, ce repository
     * ne fait que traduire en requêtes.
     *
     * ⚠ Règle blameless (CDC §2.1/§4.6) : jamais de regroupement par
     * déclarant/soignant. Les seuls axes autorisés sont service, type d'EI
     * et période — c'est pour ça que $criteria ne doit JAMAIS contenir de
     * clé "declarant" ici (StatsManager le refuse en amont).
     *
     * @param array{services?: array, pole?: ?\App\Entity\Pole} $criteria
     * @param array{statut?: \App\Enum\StatutEnum, dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable, typeEI?: \App\Enum\TypeEIEnum, gravite?: \App\Enum\GraviteEnum, eigsOnly?: bool, motCle?: string} $filters
     *
     * @return array{parService: array, parType: array, parPeriode: array}
     */
    public function aggregate(array $criteria, array $filters): array
    {
        $qb = $this->applyPerimeterAndFilters($this->createQueryBuilder('d'), $criteria, $filters);

        $parService = (clone $qb)
            ->select('s.nom AS service', 'COUNT(d.id) AS total')
            ->groupBy('s.id')
            ->getQuery()
            ->getResult();

        $parType = (clone $qb)
            ->select('d.typeEI AS type', 'COUNT(d.id) AS total')
            ->groupBy('d.typeEI')
            ->getQuery()
            ->getResult();

        // Pas de fonction DATE_TRUNC/YEAR/MONTH portable en DQL standard sans
        // extension tierce — on ne récupère que les dates (pas les entités
        // complètes) et on regroupe par mois côté PHP.
        $dates = (clone $qb)
            ->select('d.dateConstat AS dateConstat')
            ->getQuery()
            ->getResult();

        $parPeriode = [];
        foreach ($dates as $row) {
            $periode = $row['dateConstat']->format('Y-m');
            $parPeriode[$periode] = ($parPeriode[$periode] ?? 0) + 1;
        }
        ksort($parPeriode);

        return [
            'parService' => $parService,
            'parType' => $parType,
            'parPeriode' => $parPeriode,
        ];
    }

    /**
     * Traduit $criteria (périmètre) et $filters (CDC §4.6) en clauses
     * QueryBuilder — partagé par search() et aggregate() pour ne pas
     * dupliquer cette traduction deux fois.
     *
     * @param array{declarant?: \App\Entity\User, services?: array, pole?: ?\App\Entity\Pole} $criteria
     * @param array{statut?: \App\Enum\StatutEnum, dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable, typeEI?: \App\Enum\TypeEIEnum, gravite?: \App\Enum\GraviteEnum, eigsOnly?: bool, motCle?: string} $filters
     */
    private function applyPerimeterAndFilters(QueryBuilder $qb, array $criteria, array $filters): QueryBuilder
    {
        // Toujours joint : service_id est NOT NULL sur Declaration, donc cet
        // INNER JOIN ne retire jamais de résultat — et aggregate() en a besoin
        // pour parService, que le périmètre soit "pole" ou "services".
        $qb->innerJoin('d.service', 's');

        if (isset($criteria['declarant'])) {
            $qb->andWhere('d.declarant = :declarant')->setParameter('declarant', $criteria['declarant']);
        }
        if (isset($criteria['services'])) {
            $qb->andWhere('d.service IN (:services)')->setParameter('services', $criteria['services']);
        }
        if (array_key_exists('pole', $criteria)) {
            $qb->andWhere('s.pole = :pole')->setParameter('pole', $criteria['pole']);
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

        return $qb;
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
