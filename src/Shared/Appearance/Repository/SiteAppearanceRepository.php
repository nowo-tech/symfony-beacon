<?php

declare(strict_types=1);

namespace App\Shared\Appearance\Repository;

use App\Shared\Appearance\Entity\SiteAppearance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteAppearance>
 */
class SiteAppearanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteAppearance::class);
    }

    public function getOrCreate(): SiteAppearance
    {
        $appearance = $this->find(1);
        if ($appearance instanceof SiteAppearance) {
            return $appearance;
        }

        $appearance = SiteAppearance::defaults();
        $this->getEntityManager()->persist($appearance);
        $this->getEntityManager()->flush();

        return $appearance;
    }

    public function save(SiteAppearance $appearance): void
    {
        $this->getEntityManager()->persist($appearance);
        $this->getEntityManager()->flush();
    }
}
