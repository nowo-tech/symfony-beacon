<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class IssueIndexSortFunctionalTest extends DatabaseWebTestCase
{
    public function testSortAndSearchPersistInQueryString(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('sort-issues@example.com');
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $older = $this->makeIssue($project, 'Alpha issue', new DateTimeImmutable('-2 days'));
        $newer = $this->makeIssue($project, 'Bravo issue', new DateTimeImmutable('-1 hour'));
        $em->persist($older);
        $em->persist($newer);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/issues?sort=title&dir=asc&q=issue&page=1&per_page=25');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('sort=title', (string) $client->getRequest()->getRequestUri());
        self::assertStringContainsString('dir=asc', (string) $client->getRequest()->getRequestUri());
        self::assertStringContainsString('q=issue', (string) $client->getRequest()->getRequestUri());

        self::assertSelectorExists('table.issue-table[data-controller="datatable"]');
        self::assertSame('title', $crawler->filter('table.issue-table')->attr('data-datatable-sort-value'));
        self::assertSame('asc', $crawler->filter('table.issue-table')->attr('data-datatable-dir-value'));

        $titles = $crawler->filter('table.issue-table tbody tr td:first-child a')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertSame(['Alpha issue', 'Bravo issue'], $titles);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/issues?sort=title&dir=desc&q=issue');
        $titles = $crawler->filter('table.issue-table tbody tr td:first-child a')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertSame(['Bravo issue', 'Alpha issue'], $titles);

        self::assertSame('title', $crawler->filter('form.issue-filters input[name="sort"]')->attr('value'));
        self::assertSame('desc', $crawler->filter('form.issue-filters input[name="dir"]')->attr('value'));
        self::assertSame('1', $crawler->filter('form.issue-filters input[name="page"]')->attr('value'));
    }

    private function makeIssue(Project $project, string $title, DateTimeImmutable $seen): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title));
        $issue->setTitle($title);
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen($seen);
        $issue->setLastSeen($seen);
        $issue->incrementEventCount();

        return $issue;
    }
}
