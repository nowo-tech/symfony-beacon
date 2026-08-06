<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Smoke and access tests for {@see \App\Project\Controller\ProjectController}.
 */
final class ProjectControllerFunctionalTest extends DatabaseWebTestCase
{
    public function testOwnerCanSaveGovernanceAndMemberIsDenied(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('project-gov-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('project-gov-member@example.com');
        $member->setDisplayName('Governance Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/governance"]');

        $token = $crawler->filter('form[action$="/governance"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/governance', [
            '_token' => $token,
            'retention_days' => '21',
            'retention_max_events' => '5000',
            'ingest_rate_limit_per_minute' => '',
            'event_quota_daily' => '250',
            'event_quota_monthly' => '',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'governance settings saved');

        $em->clear();
        $reloaded = $em->getRepository(Project::class)->find($project->getId());
        self::assertInstanceOf(Project::class, $reloaded);
        self::assertSame(21, $reloaded->getRetentionDays());
        self::assertSame(5000, $reloaded->getRetentionMaxEvents());
        self::assertSame(250, $reloaded->getEventQuotaDaily());

        $this->login($client, $member);
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/governance', [
            '_token' => 'invalid',
            'retention_days' => '1',
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testGovernanceSaveRejectsInvalidValues(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('project-gov-invalid@example.com');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $owner);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        $token = $crawler->filter('form[action$="/governance"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/governance', [
            '_token' => $token,
            'retention_days' => '-1',
            'retention_max_events' => '',
            'ingest_rate_limit_per_minute' => '',
            'event_quota_daily' => '',
            'event_quota_monthly' => '',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'non-negative integers');
    }

    public function testDashboardSearchFiltersAccessibleProjects(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('project-search-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $other = new Project();
        $other->setName('Billing Platform');
        $other->setSlug('billing-platform-search');
        $link = new ProjectMembership();
        $link->setUser($user);
        $link->setRole(ProjectRole::Owner);
        $other->addMembership($link);
        $em->persist($other);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard?q=billing');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-tour="projects-list"]', 'Billing Platform');
        self::assertSelectorTextNotContains('[data-tour="projects-list"]', $project->getName());

        $client->request(Request::METHOD_GET, '/projects/new');
        self::assertResponseRedirects('/dashboard?new=1');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog.confirm-dialog');
    }

    public function testProjectShowRedirectsMembersToIssueIndex(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('project-show@example.com');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid());
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.issue-filters');
        self::assertSelectorTextContains('.project-nav', 'Issues');
    }
}
