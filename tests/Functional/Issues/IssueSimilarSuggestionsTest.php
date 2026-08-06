<?php

declare(strict_types=1);

namespace App\Tests\Functional\Issues;

use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Issues\Enum\IssueStatus;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IssueSimilarSuggestionsTest extends DatabaseWebTestCase
{
    public function testShowListsSimilarIssuesByTitleAndExcludesSelf(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('similar-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $current = $this->makeIssue($project, 'similar-current', 'Payment gateway timeout on checkout');
        $similar = $this->makeIssue($project, 'similar-sib', 'Payment gateway timeout in cart');
        $unrelated = $this->makeIssue($project, 'similar-other', 'Unrelated database migration failure');
        $ignored = $this->makeIssue($project, 'similar-ignored', 'Payment gateway timeout ignored');
        $ignored->setStatus(IssueStatus::Ignored);
        $em->flush();

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$current->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="similar-issues"]');
        self::assertSelectorTextContains('[data-testid="similar-issues"]', 'Payment gateway timeout in cart');
        self::assertSelectorTextNotContains('[data-testid="similar-issues"]', 'Unrelated database migration failure');
        self::assertSelectorTextNotContains('[data-testid="similar-issues"]', 'Payment gateway timeout ignored');
        self::assertSelectorTextNotContains('[data-testid="similar-issues"]', $current->getTitle());

        /** @var IssueRepository $repo */
        $repo = self::getContainer()->get(IssueRepository::class);
        $found = $repo->findSimilarIssues($current, 5);
        self::assertNotEmpty($found);
        self::assertContains($similar->getId(), array_map(static fn (Issue $i): ?int => $i->getId(), $found));
        self::assertNotContains($unrelated->getId(), array_map(static fn (Issue $i): ?int => $i->getId(), $found));
        self::assertNotContains($current->getId(), array_map(static fn (Issue $i): ?int => $i->getId(), $found));
    }

    public function testShowEmptyStateWhenNoSimilar(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('similar-empty@example.com');
        $lonely = $this->makeIssue($project, 'lonely-fp', 'Completely unique zxcvbn title');

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$lonely->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="similar-issues"]', 'No similar issues');
    }

    private function makeIssue(Project $project, string $fpSeed, string $title): Issue
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $fpSeed));
        $issue->setTitle($title);
        $issue->setCulprit('App\\Similar');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        $em->persist($issue);
        $em->flush();

        return $issue;
    }
}
