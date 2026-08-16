<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Analytics\Entity\DailyProjectStat;
use App\Identity\Command\SeedDemoCommand;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Service\PerformanceDemoSeeder;
use App\Project\Repository\ProjectRepository;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedDemoCommandTest extends DatabaseWebTestCase
{
    public function testDemoSeedCreatesUserOnceWithoutSampleTelemetry(): void
    {
        $client = self::createClient();
        $application = new Application($client->getKernel());
        $command = $application->find('app:seed-demo');
        $tester = new CommandTester($command);

        $tester->execute(['--write-client-env' => '']);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $tester->execute(['--write-client-env' => '']);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $users = self::getContainer()->get(UserRepository::class)->findBy([
            'email' => 'admin@symfony-beacon.local',
        ]);
        self::assertCount(1, $users);

        $project = self::getContainer()->get(ProjectRepository::class)->findOneBy(['slug' => SeedDemoCommand::DEMO_PROJECT_SLUG]);
        self::assertNotNull($project);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $stats = (int) $em->createQuery('SELECT COUNT(s.id) FROM '.DailyProjectStat::class.' s')->getSingleScalarResult();
        self::assertSame(0, $stats);

        $nPlusOne = $em->getRepository(PerfTransaction::class)->findOneBy([
            'project' => $project,
            'transactionName' => PerformanceDemoSeeder::NPLUS1_TRANSACTION,
        ]);
        self::assertNull($nPlusOne);

        self::assertSame(SeedDemoCommand::DEMO_PUBLIC_KEY, $project->getApiKeys()->first()->getPublicKey());
        self::assertSame(SeedDemoCommand::DEMO_PROJECT_NAME, $project->getName());
        self::assertSame(SeedDemoCommand::DEMO_PROJECT_DESCRIPTION, $project->getDescription());
    }

    public function testDemoSeedGrantsAccessToAllInstanceAdmins(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $application = new Application($client->getKernel());
        $tester = new CommandTester($application->find('app:seed-demo'));
        $tester->execute(['--write-client-env' => '']);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $extraAdmin = new User();
        $extraAdmin->setEmail('second-admin@example.com');
        $extraAdmin->setDisplayName('Second Admin');
        $extraAdmin->setRoles(['ROLE_ADMIN']);
        $extraAdmin->setPassword($hasher->hashPassword($extraAdmin, 'AdminPass1!'));
        $em->persist($extraAdmin);

        $regular = new User();
        $regular->setEmail('member-only@example.com');
        $regular->setDisplayName('Member Only');
        $regular->setRoles([]);
        $regular->setPassword($hasher->hashPassword($regular, 'MemberPass1!'));
        $em->persist($regular);
        $em->flush();

        $tester->execute(['--write-client-env' => '']);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Granted Symfony Beacon project access to 1 instance admin', $tester->getDisplay());

        $project = self::getContainer()->get(ProjectRepository::class)->findOneBy(['slug' => SeedDemoCommand::DEMO_PROJECT_SLUG]);
        self::assertNotNull($project);

        $accessible = self::getContainer()->get(ProjectRepository::class)->findAccessibleByUser($extraAdmin);
        self::assertCount(1, $accessible);
        self::assertSame($project->getId(), $accessible[0]->getId());

        $memberAccess = self::getContainer()->get(ProjectRepository::class)->findAccessibleByUser($regular);
        self::assertSame([], $memberAccess);
    }

    public function testDogfoodSkipDemoUserDoesNotCreateFixedAdmin(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = new User();
        $admin->setEmail('existing-admin@example.com');
        $admin->setDisplayName('Existing Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'AdminPass1!'));
        $em->persist($admin);
        $em->flush();

        $application = new Application($client->getKernel());
        $tester = new CommandTester($application->find('app:seed-demo'));
        $tester->execute([
            '--write-client-env' => '',
            '--skip-demo-user' => true,
        ]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Skipped demo user creation', $tester->getDisplay());

        $fixed = self::getContainer()->get(UserRepository::class)->findBy([
            'email' => 'admin@symfony-beacon.local',
        ]);
        self::assertCount(0, $fixed);

        $project = self::getContainer()->get(ProjectRepository::class)->findOneBy(['slug' => SeedDemoCommand::DEMO_PROJECT_SLUG]);
        self::assertNotNull($project);
        $accessible = self::getContainer()->get(ProjectRepository::class)->findAccessibleByUser($admin);
        self::assertCount(1, $accessible);
    }

    public function testDogfoodIgnoresFixedAdminEmailWhenEarlierRoleAdminExists(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $first = new User();
        $first->setEmail('earliest-admin@example.com');
        $first->setDisplayName('Earliest Admin');
        $first->setRoles(['ROLE_ADMIN']);
        $first->setPassword($hasher->hashPassword($first, 'AdminPass1!'));
        $em->persist($first);
        $em->flush();

        // Leftover from a prior make seed — dogfood must not prefer this over the first ROLE_ADMIN.
        $fixed = new User();
        $fixed->setEmail('admin@symfony-beacon.local');
        $fixed->setDisplayName('Fixed Demo');
        $fixed->setRoles(['ROLE_ADMIN']);
        $fixed->setPassword($hasher->hashPassword($fixed, 'admin123'));
        $em->persist($fixed);
        $em->flush();

        $clientEnv = tempnam(sys_get_temp_dir(), 'beacon-dogfood-');
        self::assertNotFalse($clientEnv);

        $application = new Application($client->getKernel());
        $tester = new CommandTester($application->find('app:seed-demo'));
        $tester->execute([
            '--skip-demo-user' => true,
            '--write-client-env' => $clientEnv,
        ]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('earliest-admin@example.com', $tester->getDisplay());

        $env = (string) file_get_contents($clientEnv);
        self::assertStringContainsString('BEACON_LOGIN_EMAIL=earliest-admin@example.com', $env);
        self::assertStringNotContainsString('BEACON_LOGIN_EMAIL=admin@symfony-beacon.local', $env);
        @unlink($clientEnv);

        $project = self::getContainer()->get(ProjectRepository::class)->findOneBy(['slug' => SeedDemoCommand::DEMO_PROJECT_SLUG]);
        self::assertNotNull($project);
        self::assertCount(1, self::getContainer()->get(ProjectRepository::class)->findAccessibleByUser($first));
        self::assertCount(1, self::getContainer()->get(ProjectRepository::class)->findAccessibleByUser($fixed));
    }
}
