<?php

declare(strict_types=1);

namespace App\Identity\Repository;

use App\Identity\Entity\InstancePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstancePermission>
 */
class InstancePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstancePermission::class);
    }

    /**
     * @return list<InstancePermission>
     */
    public function findAllOrdered(?string $query = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.name', 'ASC');

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('p.key LIKE :q OR p.name LIKE :q OR p.description LIKE :q OR p.category LIKE :q')
                ->setParameter('q', '%'.trim($query).'%');
        }

        /** @var list<InstancePermission> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function findOneByKey(string $key): ?InstancePermission
    {
        return $this->findOneBy(['key' => strtolower(trim($key))]);
    }

    /**
     * @return list<string>
     */
    public function findAllKeys(): array
    {
        /** @var list<string> $keys */
        $keys = $this->createQueryBuilder('p')
            ->select('p.key')
            ->getQuery()
            ->getSingleColumnResult();

        return $keys;
    }
}
