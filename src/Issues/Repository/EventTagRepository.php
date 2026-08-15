<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\EventTag;
use App\Project\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventTag>
 */
class EventTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventTag::class);
    }

    /**
     * Issue ids whose promoted tags match the needle on key, value, or key:value.
     *
     * @return list<int>
     */
    public function findIssueIdsMatchingTag(Project $project, string $needle): array
    {
        $projectId = $project->getId();
        if (null === $projectId || '' === $needle) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        /** @var list<int|string> $rows */
        $rows = $conn->fetchFirstColumn(
            'SELECT DISTINCT issue_id FROM event_tag'
            .' WHERE project_id = ? AND (tag_key = ? OR tag_value = ? OR CONCAT(tag_key, \':\', tag_value) = ?)',
            [$projectId, $needle, $needle, $needle],
        );

        return array_map(static fn (int|string $id): int => (int) $id, $rows);
    }
}
