<?php

declare(strict_types=1);

namespace App\Issues\Repository\Query;

use App\Issues\Entity\Event;
use App\Issues\Repository\EventTagRepository;
use App\Project\Entity\Project;
use App\Shared\Doctrine\SqlLikeEscaper;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\QueryBuilder;

/**
 * Full-text / LIKE search and tag/url/user filters shared by list and assignment queries.
 *
 * Tag and URL filters use promoted columns / event_tag — not JSON_SEARCH on payload.
 */
trait IssueSearchFilterTrait
{
    abstract protected function eventTagRepository(): EventTagRepository;

    protected function applyFullTextOrLikeQuery(QueryBuilder $qb, string $query): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $boolean = $this->toBooleanFulltextQuery($query);
            if ('' === $boolean) {
                $like = '%'.SqlLikeEscaper::escape($query).'%';
                $qb->andWhere("i.title LIKE :q ESCAPE '\\' OR i.culprit LIKE :q ESCAPE '\\'")
                    ->setParameter('q', $like);

                return;
            }

            /** @var list<int|string> $rows */
            $rows = $conn->fetchFirstColumn(
                'SELECT id FROM issue WHERE MATCH(title, culprit) AGAINST (? IN BOOLEAN MODE)',
                [$boolean],
            );
            $ids = array_map(static fn (int|string $id): int => (int) $id, $rows);
            $this->restrictToIssueIds($qb, $ids, 'fulltextIssueIds');

            return;
        }

        $like = '%'.SqlLikeEscaper::escape($query).'%';
        $qb->andWhere("i.title LIKE :q ESCAPE '\\' OR i.culprit LIKE :q ESCAPE '\\'")
            ->setParameter('q', $like);
    }

    private function toBooleanFulltextQuery(string $query): string
    {
        $tokens = preg_split('/\s+/u', $query) ?: [];
        $parts = [];
        foreach ($tokens as $token) {
            $clean = preg_replace('/[+\-><()~*"@]+/u', '', $token) ?? '';
            $clean = trim($clean);
            if (\strlen($clean) < 2) {
                continue;
            }
            $parts[] = '+'.$clean.'*';
        }

        return implode(' ', $parts);
    }

    protected function applyTagFilter(QueryBuilder $qb, Project $project, ?string $tag): void
    {
        if (null === $tag || '' === trim($tag) || null === $project->getId()) {
            return;
        }

        $ids = $this->eventTagRepository()->findIssueIdsMatchingTag($project, trim($tag));
        $this->restrictToIssueIds($qb, $ids, 'tagFilterIssueIds');
    }

    protected function applyUrlFilter(QueryBuilder $qb, Project $project, ?string $url): void
    {
        if (null === $url || '' === trim($url) || null === $project->getId()) {
            return;
        }

        $like = '%'.SqlLikeEscaper::escape(trim($url)).'%';
        $qb->andWhere(
            'EXISTS (SELECT 1 FROM '.Event::class.' eurl'
            ." WHERE eurl.issue = i AND eurl.project = :urlFilterProject AND eurl.requestUrl LIKE :urlLike ESCAPE '\\')",
        )
            ->setParameter('urlFilterProject', $project)
            ->setParameter('urlLike', $like);
    }

    protected function applyUserFilter(QueryBuilder $qb, ?string $user): void
    {
        if (null === $user || '' === trim($user)) {
            return;
        }

        $qb->andWhere(
            'EXISTS (SELECT 1 FROM '.Event::class." euser WHERE euser.issue = i AND euser.userIdentifier LIKE :userLike ESCAPE '\\')",
        )->setParameter('userLike', '%'.SqlLikeEscaper::escape(trim($user)).'%');
    }

    /** @param list<int> $ids */
    private function restrictToIssueIds(QueryBuilder $qb, array $ids, string $param): void
    {
        if ([] === $ids) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere('i.id IN (:'.$param.')')->setParameter($param, $ids);
    }
}
