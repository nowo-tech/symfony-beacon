<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testNonAdminCannotAccessAdminProjects(): void
    {
        [$client, $user] = $this->bootWithDemoProject('non-admin-projects@example.com');
        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin/projects');
        self::assertResponseStatusCodeSame(403);
    }
}
