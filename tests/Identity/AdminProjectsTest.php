<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Entity\UserGroup;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminProjectsTest extends DatabaseWebTestCase
{
    public function testAdminCanListAndOpenProject(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-projects-list@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/admin/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Projects');
        self::assertSelectorTextContains('body', $project->getName());

        $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $project->getName());
        self::assertSelectorExists('form[action$="/members"]');
        self::assertSelectorTextContains('body', 'Audit timeline');
        self::assertSelectorTextContains('body', 'No recorded project actions match the current filters.');
    }

    public function testAdminCanCreateProjectAndAddMember(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('admin-projects-create@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $invitee = new User();
        $invitee->setEmail('admin-project-invitee@example.com');
        $invitee->setDisplayName('Invitee');
        $invitee->setPassword($hasher->hashPassword($invitee, 'secret'));
        $em->persist($invitee);
        $em->flush();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('New project')->form([
            'name' => 'Billing API',
            'description' => 'From admin',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Billing API');

        $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'billing-api']);
        self::assertInstanceOf(Project::class, $project);

        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        $token = $crawler->filter('form[action$="/members"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/projects/'.$project->getUuid().'/members', [
            '_token' => $token,
            'email' => 'admin-project-invitee@example.com',
            'role' => 'admin',
        ]);
        self::assertResponseRedirects('/admin/projects/'.$project->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'admin-project-invitee@example.com');

        $em->clear();
        $membership = $em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $invitee->getId(),
        ]);
        self::assertSame(ProjectRole::Admin, $membership?->getRole());
    }

    public function testAdminCanLinkGroupAndDeleteProject(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-projects-delete@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $group = new UserGroup();
        $group->setName('Ops');
        $group->setSlug('ops-admin-projects');
        $em->persist($group);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        $token = $crawler->filter('form[action$="/groups"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/projects/'.$project->getUuid().'/groups', [
            '_token' => $token,
            'group' => $group->getUuid(),
            'role' => 'member',
        ]);
        self::assertResponseRedirects('/admin/projects/'.$project->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ops');

        $em->clear();
        $access = $em->getRepository(ProjectGroupAccess::class)->findOneBy([
            'project' => $project->getId(),
            'userGroup' => $group->getId(),
        ]);
        self::assertInstanceOf(ProjectGroupAccess::class, $access);

        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        $deleteForm = $crawler->filter('form[action$="/delete"]');
        self::assertGreaterThan(0, $deleteForm->count());
        $token = $deleteForm->filter('input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/projects/'.$project->getUuid().'/delete', [
            '_token' => $token,
            'confirmation' => $project->getName(),
        ]);
        self::assertResponseRedirects('/admin/projects');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Project deleted');

        $em->clear();
        self::assertNull($em->getRepository(Project::class)->find($project->getId()));
    }

    public function testAdminProjectAuditTimelineCanBeFiltered(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-projects-audit@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $otherProject = new Project();
        $otherProject->setName('Other Project');
        $otherProject->setSlug('other-project-audit');
        $em->persist($otherProject);

        $this->seedProjectAction(
            $em,
            $admin,
            $project,
            UserActionType::ProjectSuspended,
            new DateTimeImmutable('2026-07-20 09:00:00'),
            ['project_name' => $project->getName()],
        );
        $this->seedProjectAction(
            $em,
            $admin,
            $project,
            UserActionType::ProjectResumed,
            new DateTimeImmutable('2026-07-21 11:00:00'),
            ['project_name' => $project->getName()],
        );
        $this->seedProjectAction(
            $em,
            $admin,
            $project,
            UserActionType::ProjectMemberAdded,
            new DateTimeImmutable('2026-07-21 08:00:00'),
            [
                'project' => $project->getName(),
                'role' => 'member',
            ],
        );
        $this->seedProjectAction(
            $em,
            $admin,
            $otherProject,
            UserActionType::ProjectDeleted,
            new DateTimeImmutable('2026-07-21 12:00:00'),
            ['project_name' => $otherProject->getName()],
        );
        $em->flush();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Project ingest resumed');
        self::assertSelectorTextContains('body', 'Project ingest suspended');
        self::assertSelectorTextContains('body', 'Project member added');
        self::assertSelectorTextNotContains('body', 'Other Project');

        $entries = $crawler->filter('#project-audit-timeline [data-testid="project-audit-entry"]');
        self::assertSame(3, $entries->count());
        self::assertSame('Project ingest resumed', trim($entries->eq(0)->filter('span.font-medium')->text()));
        self::assertSame('Project member added', trim($entries->eq(1)->filter('span.font-medium')->text()));
        self::assertSame('Project ingest suspended', trim($entries->eq(2)->filter('span.font-medium')->text()));

        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid().'?action=project.suspended');
        self::assertResponseIsSuccessful();
        $entries = $crawler->filter('#project-audit-timeline [data-testid="project-audit-entry"]');
        self::assertSame(1, $entries->count());
        self::assertSame('Project ingest suspended', trim($entries->eq(0)->filter('span.font-medium')->text()));

        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid().'?from=2026-07-21&to=2026-07-21');
        self::assertResponseIsSuccessful();
        $entries = $crawler->filter('#project-audit-timeline [data-testid="project-audit-entry"]');
        self::assertSame(2, $entries->count());
        self::assertSame('Project ingest resumed', trim($entries->eq(0)->filter('span.font-medium')->text()));
        self::assertSame('Project member added', trim($entries->eq(1)->filter('span.font-medium')->text()));
    }

    public function testNonAdminCannotAccessAdminProjects(): void
    {
        [$client, $user] = $this->bootWithDemoProject('non-admin-projects@example.com');
        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin/projects');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string, scalar|null> $extraContext
     */
    private function seedProjectAction(
        EntityManagerInterface $em,
        User $actor,
        Project $project,
        UserActionType $actionType,
        DateTimeImmutable $createdAt,
        array $extraContext = [],
    ): void {
        $action = new UserAction();
        $action->setAction($actionType);
        $action->setActor($actor);
        $action->setSubjectUser($actor);
        $action->setContext(['project_uuid' => $project->getUuid()] + $extraContext);
        $this->setUserActionCreatedAt($action, $createdAt);
        $em->persist($action);
    }

    private function setUserActionCreatedAt(UserAction $action, DateTimeImmutable $createdAt): void
    {
        $property = new ReflectionProperty($action, 'createdAt');
        $property->setValue($action, $createdAt);
    }
}
