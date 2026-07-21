<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectViewerRoleTest extends DatabaseWebTestCase
{
    public function testViewerCanOpenIssueButCannotChangeStatus(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('viewer-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $viewer = new User();
        $viewer->setEmail('viewer-user@example.com');
        $viewer->setDisplayName('Viewer');
        $viewer->setPassword($hasher->hashPassword($viewer, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($viewer);
        $membership->setRole(ProjectRole::Viewer);
        $project->addMembership($membership);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'viewer-issue'));
        $issue->setTitle('Viewer issue');
        $issue->setCulprit('viewer.php');
        $issue->setLevel('error');
        $em->persist($viewer);
        $em->persist($issue);
        $em->flush();

        $this->login($client, $viewer);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="viewer-readonly"]');
        self::assertSelectorNotExists('form.issue-status-actions__form');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/status', [
            '_token' => 'invalid',
            'status' => 'resolved',
        ]);
        self::assertResponseStatusCodeSame(403);
    }
}
