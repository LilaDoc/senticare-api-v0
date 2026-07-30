<?php

namespace App\Repository;

use App\Entity\LogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LogEntry>
 */
class LogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogEntry::class);
    }

    /**
     * @param array{type?: \App\Enum\LogTypeEnum, user?: \App\Entity\User, dateFrom?: \DateTimeImmutable, dateTo?: \DateTimeImmutable} $filters
     *
     * @return LogEntry[]
     */
    public function search(array $filters): array
    {
        $qb = $this->applyFilters($this->createQueryBuilder('l'), $filters);

        return $qb->orderBy('l.createdAt', 'DESC')->getQuery()->getResult();
    }

    private function applyFilters(QueryBuilder $qb, array $filters): QueryBuilder
    {
        if (isset($filters['type'])) {
            $qb->andWhere('l.type = :type')->setParameter('type', $filters['type']);
        }
        if (isset($filters['user'])) {
            $qb->andWhere('l.user = :user')->setParameter('user', $filters['user']);
        }
        if (isset($filters['dateFrom'])) {
            $qb->andWhere('l.createdAt >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom']);
        }
        if (isset($filters['dateTo'])) {
            $qb->andWhere('l.createdAt <= :dateTo')->setParameter('dateTo', $filters['dateTo']);
        }

        return $qb;
    }
}
