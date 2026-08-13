<?php

declare(strict_types=1);

namespace App\Issues\Repository\Query;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Shared\Doctrine\SqlLikeEscaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\QueryBuilder;

/**
 * Full-text / LIKE search and tag/url/user payload filters shared by list and assignment queries.
 *
 * @phpstan-require-extends ServiceEntityRepository<Issue>
 */
trait IssueSearchFilterTrait
{
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

        $needle = trim($tag);
        $ids = $this->issueIdsMatchingPayload($project, $needle, useJsonSearch: true);
        $this->restrictToIssueIds($qb, $ids, 'tagFilterIssueIds');
    }

    protected function applyUrlFilter(QueryBuilder $qb, Project $project, ?string $url): void
    {
        if (null === $url || '' === trim($url) || null === $project->getId()) {
            return;
        }

        $needle = trim($url);
        $ids = $this->issueIdsMatchingPayload($project, $needle, useJsonSearch: false);
        $this->restrictToIssueIds($qb, $ids, 'urlFilterIssueIds');
    }

    protected function applyUserFilter(QueryBuilder $qb, ?string $user): void
    {
        if (null === $user || '' === trim($user)) {
            return;
        }

        $qb->andWhere(
            'EXISTS (SELECT 1 FROM '.Event::class." ue WHERE ue.issue = i AND ue.userIdentifier LIKE :userLike ESCAPE '\\')",
        )->setParameter('userLike', '%'.SqlLikeEscaper::escape(trim($user)).'%');
    }

    /** @return list<int> */
    private function issueIdsMatchingPayload(Project $project, string $needle, bool $useJsonSearch): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();
        $projectId = $project->getId();
        if (null === $projectId) {
            return [];
        }

        $isSqlite = $platform instanceof SQLitePlatform;
        $isMysql = $platform instanceof AbstractMySQLPlatform;

        if ($useJsonSearch && $isMysql) {
            $sql = 'SELECT DISTINCT e.issue_id FROM event e'
                .' INNER JOIN issue i ON i.id = e.issue_id'
                .' WHERE i.project_id = ? AND JSON_SEARCH(e.payload, \'one\', ?) IS NOT NULL';
            $params = [$projectId, $needle];
        } else {
            $like = '%'.SqlLikeEscaper::escape($needle).'%';
            if ($isSqlite) {
                // SQLite: ESCAPE '\' — one backslash as the escape character.
                $sql = 'SELECT DISTINCT e.issue_id FROM event e'
                    .' INNER JOIN issue i ON i.id = e.issue_id'
                    ." WHERE i.project_id = ? AND CAST(e.payload AS TEXT) LIKE ? ESCAPE '\\'";
            } else {
                // MySQL: string literal '\\' is one backslash (NO_BACKSLASH_ESCAPES off).
                // A single-quoted ESCAPE '\' is a syntax error (see SQLSTATE 1064 near ''\'').
                $sql = 'SELECT DISTINCT e.issue_id FROM event e'
                    .' INNER JOIN issue i ON i.id = e.issue_id'
                    ." WHERE i.project_id = ? AND CAST(e.payload AS CHAR) LIKE ? ESCAPE '\\\\'";
            }
            $params = [$projectId, $like];
        }

        /** @var list<int|string> $rows */
        $rows = $conn->fetchFirstColumn($sql, $params);

        return array_map(static fn (int|string $id): int => (int) $id, $rows);
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
