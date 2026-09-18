<?php

declare(strict_types=1);

namespace App\Shared\Legal\Repository;

use App\Shared\Legal\Entity\LegalDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalDocument>
 */
class LegalDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalDocument::class);
    }

    public function findOneBySlugAndLocale(string $slug, string $locale): ?LegalDocument
    {
        $found = $this->findOneBy(['slug' => $slug, 'locale' => $locale]);

        return $found instanceof LegalDocument ? $found : null;
    }

    /**
     * @return array<string, array<string, true>>
     */
    public function customSlugLocaleMap(): array
    {
        /** @var list<array{slug: string, locale: string}> $rows */
        $rows = $this->createQueryBuilder('document')
            ->select('document.slug AS slug', 'document.locale AS locale')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['slug']][$row['locale']] = true;
        }

        return $map;
    }

    public function save(LegalDocument $document): void
    {
        $manager = $this->getEntityManager();
        $manager->persist($document);
        $manager->flush();
    }

    public function remove(LegalDocument $document): void
    {
        $manager = $this->getEntityManager();
        $manager->remove($document);
        $manager->flush();
    }
}
