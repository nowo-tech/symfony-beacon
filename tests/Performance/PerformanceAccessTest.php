<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use App\Identity\Entity\User;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PerformanceAccessTest extends DatabaseWebTestCase
{
    public function testMemberCanOpenPerformance(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance');
        self::assertResponseIsSuccessful();
    }

    public function testStrangerIsForbidden(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('owner-perf@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $stranger = new User();
        $stranger->setEmail('stranger-perf@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance');
        self::assertResponseStatusCodeSame(403);
    }

    public function testServerSidePaginationLimitsRows(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('page-perf@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= 12; ++$i) {
            $em->persist($this->makeTransaction(
                $project,
                \sprintf('GET /page-%02d', $i),
                new DateTimeImmutable(\sprintf('-%d hours', 13 - $i)),
            ));
        }
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance?per_page=10&page=1');
        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('table tbody tr'));
        self::assertSelectorExists('.table-pagination');
        self::assertSelectorExists('a.table-pagination__link[href*="page=2"]');

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance?per_page=10&page=2');
        self::assertCount(2, $crawler->filter('table tbody tr'));
    }

    private function makeTransaction(Project $project, string $name, DateTimeImmutable $receivedAt): PerfTransaction
    {
        $tx = new PerfTransaction();
        $tx->setProject($project);
        $tx->setEventId(bin2hex(random_bytes(16)));
        $tx->setTransactionName($name);
        $tx->setDurationMs(12.5);
        $tx->setSpanCount(1);
        $tx->setPayload([]);
        $tx->setReceivedAt($receivedAt);

        return $tx;
    }
}
