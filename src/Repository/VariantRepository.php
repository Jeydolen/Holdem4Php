<?php

namespace App\Repository;

use App\Entity\Variant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Variant>
 */
class VariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Variant::class);
    }

    /**
     * @return Variant[]
     */
    public function findAllVariantsOrderedByPhasePriority(): array
    {
        return $this->createQueryBuilder("v")
            ->join("v.variantPhases", "vp")
            ->join("vp.phase", "p")
            ->orderBy("p.priority", "asc")
            ->getQuery()
            ->getResult();
    }
}
