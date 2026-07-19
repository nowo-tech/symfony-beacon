<?php

declare(strict_types=1);

namespace App\Performance\Repository;

use App\Performance\Entity\PerfSpan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PerfSpan>
 */
class PerfSpanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PerfSpan::class);
    }
}
