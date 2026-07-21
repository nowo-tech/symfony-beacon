<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedPlatformCommandTest extends DatabaseWebTestCase
{
    public function testPlatformSeedIsIdempotentAndCreatesNoDemoUser(): void
    {
        $client = self::createClient();
        $application = new Application($client->getKernel());
        $command = $application->find('app:seed-platform');
        $tester = new CommandTester($command);

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $users = self::getContainer()->get(UserRepository::class)->findBy([
            'email' => 'admin@symfony-beacon.local',
        ]);
        self::assertSame([], $users);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $count = (int) $em->createQuery('SELECT COUNT(u.id) FROM '.User::class.' u')->getSingleScalarResult();
        self::assertSame(0, $count);

        $configCount = (int) $em->createQuery(
            'SELECT COUNT(c.id) FROM Nowo\CookieConsentBundle\Entity\CookieConsentConfig c',
        )->getSingleScalarResult();
        self::assertGreaterThanOrEqual(1, $configCount);

        $definitionCount = (int) $em->createQuery(
            'SELECT COUNT(d.id) FROM Nowo\CookieConsentBundle\Entity\CookieDefinition d',
        )->getSingleScalarResult();
        self::assertGreaterThanOrEqual(4, $definitionCount);
    }
}
