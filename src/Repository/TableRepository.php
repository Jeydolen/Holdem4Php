<?php

namespace App\Repository;

use App\Entity\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Table>
 */
class TableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Table::class);
    }

    /**
     * @return Table[] Returns an array of Table objects
     */
    public function findByVariantCriterias(array $criterias, int $limit): array
    {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.variant', 'v');

        foreach ($criterias as $criteria_name => $criteria_value) {
            $qb->andWhere("v.$criteria_name = :$criteria_name")
                ->setParameter($criteria_name, $criteria_value);
        }

        return $qb->setMaxResults(10)->getQuery()->getResult();
    }
}
