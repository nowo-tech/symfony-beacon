<?php

declare(strict_types=1);

namespace App\Tests\Functional\Issues;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueSavedView;
use App\Issues\Enum\IssueStatus;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Access and happy-path coverage for {@see \App\Issues\Controller\IssueController::index()}.
 */
final class IssueIndexAccessFunctionalTest extends DatabaseWebTestCase
{
    public function testIssueIndexRequiresAuthAndDeniesStrangers(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('issue-access-owner@example.com');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertTrue($client->getResponse()->isRedirection());
        self::assertMatchesRegularExpression('#/(en/)?login$#', (string) $client->getResponse()->headers->get('Location'));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $stranger = new User();
        $stranger->setEmail('issue-access-stranger@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIssueIndexEmptyStateAndAssigneeFilter(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('issue-index-empty@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $mate = new User();
        $mate->setEmail('issue-index-mate@example.com');
        $mate->setDisplayName('Mate');
        $mate->setPassword($hasher->hashPassword($mate, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($mate);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $mine = $this->makeIssue($project, 'Assigned to owner', $owner);
        $theirs = $this->makeIssue($project, 'Assigned to mate', $mate);
        $em->persist($mate);
        $em->persist($mine);
        $em->persist($theirs);
        $em->flush();

        $this->login($client, $owner);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?status=resolved');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No issues match these filters');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?assignee='.$mate->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.issue-table', 'Assigned to mate');
        self::assertSelectorTextNotContains('table.issue-table', 'Assigned to owner');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues?assignee=unassigned');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No issues match these filters');
    }

    public function testMemberCanSaveApplyAndDeleteSavedView(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('issue-view-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($this->makeIssue($project, 'Filtered error issue', null, 'error'));
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?level=error&status=unresolved',
        );
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.issue-table', 'Filtered error issue');

        $token = $crawler->filter('form[action$="/issues/views"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/views', [
            '_token' => $token,
            'name' => 'Error unresolved',
            'level' => 'error',
            'status' => 'unresolved',
        ]);
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('level=error', $location);
        $client->followRedirect();
        self::assertSelectorTextContains('#saved-view-select', 'Error unresolved');

        $views = $em->getRepository(IssueSavedView::class)->findBy(['project' => $project, 'user' => $owner]);
        self::assertCount(1, $views);
        $viewUuid = $views[0]->getUuid();

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.issue-table', 'Filtered error issue');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/views/'.$viewUuid);
        self::assertResponseRedirects();
        self::assertStringContainsString('level=error', (string) $client->getResponse()->headers->get('Location'));

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('table.issue-table', 'Filtered error issue');

        $deleteToken = $crawler->filter('form[action$="/issues/views/'.$viewUuid.'/delete"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/views/'.$viewUuid.'/delete', [
            '_token' => $deleteToken,
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues');
        $client->followRedirect();
        self::assertSelectorTextNotContains('#saved-view-select', 'Error unresolved');

        $em->clear();
        self::assertNull($em->getRepository(IssueSavedView::class)->findOneBy(['uuid' => $viewUuid]));
    }

    public function testViewerCanBrowseIssueIndexButCannotSaveViews(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('issue-viewer-index@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $viewer = new User();
        $viewer->setEmail('issue-viewer@example.com');
        $viewer->setDisplayName('Viewer');
        $viewer->setPassword($hasher->hashPassword($viewer, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($viewer);
        $membership->setRole(ProjectRole::Viewer);
        $project->addMembership($membership);
        $em->persist($viewer);
        $em->persist($this->makeIssue($project, 'Read-only issue', null));
        $em->flush();

        $this->login($client, $viewer);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.issue-table', 'Read-only issue');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/views', [
            '_token' => 'invalid',
            'name' => 'Blocked view',
        ]);
        self::assertResponseStatusCodeSame(403);
        unset($owner);
    }

    private function makeIssue(Project $project, string $title, ?User $assignee, string $level = 'error'): Issue
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title));
        $issue->setTitle($title);
        $issue->setCulprit('demo.php');
        $issue->setLevel($level);
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
