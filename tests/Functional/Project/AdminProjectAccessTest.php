<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Identity\Entity\User;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Admin project access mutations (ROLE_ADMIN surfaces under /admin/projects).
 */
final class AdminProjectAccessTest extends DatabaseWebTestCase
{
    public function testAdminCanChangeMemberRole(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-access-role@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $member = new User();
        $member->setEmail('admin-access-member@example.com');
        $member->setDisplayName('Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $em->persist($member);
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();
        $memberUuid = $member->getUuid();
        $membershipId = $membership->getId();
        self::assertNotNull($membershipId);
        self::assertNotSame('', $memberUuid);

        $em->clear();
        $admin = $em->getRepository(User::class)->find($admin->getId());
        $project = $em->getRepository(\App\Project\Entity\Project::class)->find($project->getId());
        self::assertNotNull($admin);
        self::assertNotNull($project);

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/projects/'.$project->getUuid());
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(\sprintf('form[action*="/members/%s/role"]', $memberUuid));
        self::assertGreaterThan(0, $form->count(), 'Expected member role form on admin project show');
        $token = $form->filter('input[name="_token"]')->attr('value');

        $client->request(
            Request::METHOD_POST,
            '/admin/projects/'.$project->getUuid().'/members/'.$memberUuid.'/role',
            [
                '_token' => $token,
                'role' => 'admin',
            ],
        );
        self::assertResponseRedirects('/admin/projects/'.$project->getUuid());

        $em->clear();
        $updated = $em->getRepository(ProjectMembership::class)->find($membershipId);
        self::assertInstanceOf(ProjectMembership::class, $updated);
        self::assertSame(ProjectRole::Admin, $updated->getRole());
    }
}
