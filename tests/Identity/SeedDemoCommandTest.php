<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Analytics\Entity\DailyProjectStat;
use App\Identity\Command\SeedDemoCommand;
use App\Identity\Repository\UserRepository;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Service\PerformanceDemoSeeder;
use App\Project\Repository\ProjectRepository;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

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

        $project = self::getContainer()->get(ProjectRepository::class)->findOneBy(['slug' => 'demo']);
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
    }
}
