<?php

declare(strict_types=1);

namespace App\Shared\Settings\Repository;

use App\Shared\Settings\Entity\InstanceSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstanceSettings>
 */
class InstanceSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstanceSettings::class);
    }

    public function getOrCreate(): InstanceSettings
    {
        $settings = $this->find(1);
        if ($settings instanceof InstanceSettings) {
            return $settings;
        }

        $settings = InstanceSettings::defaults();
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();

        return $settings;
    }

    public function save(InstanceSettings $settings): void
    {
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();
    }
}
