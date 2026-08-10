<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Service\InstanceRbacSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AdminInstanceRbacTest extends DatabaseWebTestCase
{
    public function testRolesAndPermissionsUiRequiresAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('rbac-user@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/roles');
        self::assertResponseStatusCodeSame(403);

        $client->request(Request::METHOD_GET, '/admin/permissions');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanManageRolePermissionsAndUsers(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('rbac-admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setDisplayName('RBAC Admin');
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->flush();

        self::getContainer()->get(InstanceRbacSeeder::class)->seedIfEmpty();
        $em->clear();
        self::assertCount(8, $em->getRepository(InstancePermission::class)->findAll());
        self::assertNull($em->getRepository(InstancePermission::class)->findOneBy(['key' => 'admin.hub.view']));
        self::assertNull($em->getRepository(InstancePermission::class)->findOneBy(['key' => 'admin.permissions.manage']));
        self::assertNotNull($em->getRepository(InstancePermission::class)->findOneBy(['key' => 'project.view']));
        self::assertNotNull($em->getRepository(InstancePermission::class)->findOneBy(['key' => 'project.issues.triage']));
        self::assertNotNull($em->getRepository(InstancePermission::class)->findOneBy(['key' => 'project.delete']));

        // Legacy admin-operator InstanceRoles must not be re-seeded.
        self::assertNull($em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_SUPPORT']));
        self::assertNull($em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_OPS_VIEWER']));

        /** @var InstanceRole $projectViewer */
        $projectViewer = $em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_PROJECT_VIEWER']);
        self::assertInstanceOf(InstanceRole::class, $projectViewer);
        self::assertTrue($projectViewer->isSystem());
        self::assertTrue($projectViewer->hasPermissionKey('project.view'));
        self::assertFalse($projectViewer->hasPermissionKey('project.issues.triage'));

        /** @var InstanceRole $projectOwner */
        $projectOwner = $em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_PROJECT_OWNER']);
        self::assertInstanceOf(InstanceRole::class, $projectOwner);
        self::assertTrue($projectOwner->hasPermissionKey('project.delete'));
        self::assertCount(4, $em->getRepository(InstanceRole::class)->findBy(['system' => true]));

        $this->login($client, $admin);

        $client->request(Request::METHOD_GET, '/admin/permissions');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Permissions');
        self::assertSelectorExists('[data-testid="admin-permission-row"]');
        self::assertSelectorTextContains('body', 'View project');
        self::assertSelectorTextContains('body', 'Triage issues');
        self::assertSelectorTextContains('body', 'Delete project');
        self::assertSelectorExists('[data-testid="admin-permission-create"]');
        self::assertSelectorExists('[data-testid="admin-permission-edit"]');
        self::assertSelectorExists('dialog.confirm-dialog--md');
        // Closed dialogs must not emit open-on-connect (Stimulus Boolean treats empty attr as true).
        self::assertSelectorNotExists('[data-confirm-dialog-open-on-connect-value]');

        $client->request(Request::METHOD_GET, '/admin/permissions/new');
        self::assertResponseRedirects('/admin/permissions?new=1');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="admin-permission-create"]');
        self::assertCount(1, $crawler->filter('[data-confirm-dialog-open-on-connect-value="true"]'));

        /** @var InstancePermission $projectViewForEdit */
        $projectViewForEdit = $em->getRepository(InstancePermission::class)->findOneBy(['key' => 'project.view']);
        self::assertInstanceOf(InstancePermission::class, $projectViewForEdit);
        $client->request(Request::METHOD_GET, '/admin/permissions/'.$projectViewForEdit->getUuid().'/edit');
        self::assertResponseRedirects('/admin/permissions?edit='.$projectViewForEdit->getUuid());
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-confirm-dialog-open-on-connect-value="true"]'));

        $client->request(Request::METHOD_GET, '/admin/roles');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Roles');
        self::assertSelectorExists('[data-testid="admin-role-create"]');
        self::assertSelectorExists('#role-create-dialog-title');

        $client->request(Request::METHOD_GET, '/admin/roles/new');
        self::assertResponseRedirects('/admin/roles?new=1');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-confirm-dialog-open-on-connect-value="true"]'));

        $form = $crawler->filter('form[action$="/admin/roles/new"]')->form([
            'admin_instance_role[name]' => 'Matrix helper',
            'admin_instance_role[code]' => 'ROLE_MATRIX_HELPER',
            'admin_instance_role[description]' => 'Settings manage helper',
            'admin_instance_role[enabled]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Matrix helper');

        /** @var InstanceRole $role */
        $role = $em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_MATRIX_HELPER']);
        self::assertInstanceOf(InstanceRole::class, $role);

        /** @var InstancePermission $settingsManage */
        $settingsManage = $em->getRepository(InstancePermission::class)->findOneBy(['key' => 'project.settings.manage']);
        self::assertInstanceOf(InstancePermission::class, $settingsManage);

        $client->request(Request::METHOD_GET, '/admin/roles/'.$role->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="admin-role-overview"]');

        $crawler = $client->request(Request::METHOD_GET, '/admin/roles/'.$role->getUuid().'/permissions');
        self::assertResponseIsSuccessful();
        $csrf = $crawler->filter('[data-testid="admin-role-permissions"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/roles/'.$role->getUuid().'/permissions', [
            '_token' => $csrf,
            'permission_ids' => [(string) $settingsManage->getId()],
        ]);
        self::assertResponseRedirects('/admin/roles/'.$role->getUuid().'/permissions');
        $client->followRedirect();

        $em->clear();
        $role = $em->getRepository(InstanceRole::class)->find($role->getId());
        self::assertInstanceOf(InstanceRole::class, $role);
        self::assertTrue($role->hasPermissionKey('project.settings.manage'));

        $crawler = $client->request(Request::METHOD_GET, '/admin/roles/'.$role->getUuid().'/users');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="admin-role-edit"]');
        self::assertSelectorExists('#role-edit-dialog-title');

        $client->request(Request::METHOD_GET, '/admin/roles/'.$role->getUuid().'/edit');
        self::assertResponseRedirects('/admin/roles/'.$role->getUuid().'?edit=1');

        $editForm = $crawler->filter('form[action$="/edit"]')->form([
            'admin_instance_role[name]' => 'Matrix helper renamed',
            'admin_instance_role[description]' => 'Updated via modal',
            'admin_instance_role[enabled]' => '1',
            '_return' => 'admin_roles_users',
        ]);
        $client->submit($editForm);
        self::assertResponseRedirects('/admin/roles/'.$role->getUuid().'/users');

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Matrix helper renamed');

        $userForm = $crawler->filter('[data-testid="admin-role-users"] form')->last()->form([
            'email' => $admin->getEmail(),
        ]);
        $client->submit($userForm);
        self::assertResponseRedirects('/admin/roles/'.$role->getUuid().'/users');

        $em->clear();
        $role = $em->getRepository(InstanceRole::class)->find($role->getId());
        self::assertInstanceOf(InstanceRole::class, $role);
        self::assertCount(1, $role->getUsers());
        self::assertSame('ROLE_MATRIX_HELPER', $role->getCode());
        self::assertSame('Matrix helper renamed', $role->getName());
    }
}
