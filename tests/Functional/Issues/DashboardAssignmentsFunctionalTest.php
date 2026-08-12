<?php

declare(strict_types=1);

namespace App\Tests\Functional\Issues;

use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Project\Entity\Project;
use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DashboardAssignmentsFunctionalTest extends DatabaseWebTestCase
{
    public function testListsMineTeammatesAndUnassignedAcrossProjects(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('assign-panel-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $mate = new User();
        $mate->setEmail('assign-panel-mate@example.com');
        $mate->setDisplayName('Teammate');
        $mate->setPassword($hasher->hashPassword($mate, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($mate);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $mine = $this->makeIssue($project, 'Mine issue', $owner);
        $theirs = $this->makeIssue($project, 'Teammate issue', $mate);
        $open = $this->makeIssue($project, 'Unassigned issue', null);

        $em->persist($mate);
        $em->persist($mine);
        $em->persist($theirs);
        $em->persist($open);
        $em->flush();

        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $owner);

        $client->request(Request::METHOD_GET, '/dashboard/assignments');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="assignments-filters"]');
        self::assertSelectorTextContains('[data-testid="assignments-results"]', 'Mine issue');
        self::assertSelectorTextNotContains('[data-testid="assignments-results"]', 'Teammate issue');
        self::assertSelectorTextNotContains('[data-testid="assignments-results"]', 'Unassigned issue');
        self::assertSelectorExists('#dashboard-menu-navigation');
        self::assertSelectorTextContains('#dashboard-menu-navigation', 'Assignments');

        $client->request(Request::METHOD_GET, '/dashboard/assignments?scope=teammates');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="assignments-results"]', 'Teammate issue');

        $client->request(Request::METHOD_GET, '/dashboard/assignments?scope=unassigned');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="assignments-results"]', 'Unassigned issue');

        $client->request(Request::METHOD_GET, '/dashboard/assignments?scope=all&status=unresolved');
        self::assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('Mine issue', $html);
        self::assertStringContainsString('Teammate issue', $html);
        self::assertStringContainsString('Unassigned issue', $html);
    }

    private function makeIssue(Project $project, string $title, ?User $assignee): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title));
        $issue->setTitle($title);
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        if ($assignee instanceof User) {
            $issue->setAssignee($assignee);
        }

        return $issue;
    }
}
