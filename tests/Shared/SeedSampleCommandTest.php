<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedSampleCommandTest extends DatabaseWebTestCase
{
    public function testDevSampleThenPurgeLeavesOtherProjectIntact(): void
    {
        $client = static::createClient();
        $application = new Application($client->getKernel());

        $demoTester = new CommandTester($application->find('app:seed-demo'));
        $demoTester->execute(['--write-client-env' => '']);
        self::assertSame(0, $demoTester->getStatusCode(), $demoTester->getDisplay());

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $other = new Project();
        $other->setName('Other');
        $other->setSlug('other-keep');
        $membership = new ProjectMembership();
        $membership->setUser(static::getContainer()->get(\App\Identity\Repository\UserRepository::class)->findOneByEmail('admin@symfony-beacon.local'));
        $membership->setRole(ProjectRole::Owner);
        $other->addMembership($membership);
        $em->persist($other);
        $em->flush();
        $otherId = $other->getId();

        $otherIssue = new Issue();
        $otherIssue->setProject($other);
        $otherIssue->setFingerprint(hash('sha256', 'other-keep'));
        $otherIssue->setTitle('Keep me');
        $otherIssue->setCulprit('Other\\Keep');
        $em->persist($otherIssue);
        $em->flush();

        $sampleTester = new CommandTester($application->find('app:seed-sample'));
        $sampleTester->execute(['--size' => 'dev', '--project' => 'demo']);
        self::assertSame(0, $sampleTester->getStatusCode(), $sampleTester->getDisplay());
        self::assertStringContainsString('Mercure', $sampleTester->getDisplay());

        $settings = static::getContainer()->get(\App\Shared\Settings\Repository\InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($settings->isMercureEnabled());
        self::assertTrue($settings->hasMercureJwtSecret());
        self::assertNotEmpty($settings->getMercureUrl());
        self::assertNotEmpty($settings->getMercurePublicUrl());
        self::assertTrue(static::getContainer()->get(\App\Shared\Mercure\ConfiguredMercure::class)->isEnabled());

        $conn = $em->getConnection();
        foreach (['mercure_url', 'mercure_public_url', 'mercure_jwt_secret'] as $column) {
            $raw = $conn->fetchOne(\sprintf('SELECT %s FROM instance_settings WHERE id = 1', $column));
            self::assertIsString($raw, $column);
            self::assertStringEndsWith('<ENC>', $raw, $column);
        }

        $demoIssueCount = (int) $em->createQuery(
            'SELECT COUNT(i.id) FROM '.Issue::class.' i WHERE i.project = :p',
        )->setParameter('p', $em->getRepository(Project::class)->findOneBy(['slug' => 'demo']))
            ->getSingleScalarResult();
        self::assertGreaterThanOrEqual(30, $demoIssueCount);

        $sampleTester->execute(['--purge' => true, '--project' => 'demo']);
        self::assertSame(0, $sampleTester->getStatusCode(), $sampleTester->getDisplay());

        $em->clear();

        $demoAfter = (int) $em->createQuery(
            'SELECT COUNT(i.id) FROM '.Issue::class.' i JOIN i.project p WHERE p.slug = :slug',
        )->setParameter('slug', 'demo')->getSingleScalarResult();
        self::assertSame(0, $demoAfter);

        $kept = $em->find(Issue::class, $otherIssue->getId());
        self::assertNotNull($kept);
        self::assertNotNull($em->find(Project::class, $otherId));
    }

    public function testHugeRequiresForce(): void
    {
        $client = static::createClient();
        $application = new Application($client->getKernel());
        (new CommandTester($application->find('app:seed-demo')))->execute(['--write-client-env' => '']);

        $tester = new CommandTester($application->find('app:seed-sample'));
        $tester->execute(['--size' => 'huge']);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('--force', $tester->getDisplay());
    }
}
