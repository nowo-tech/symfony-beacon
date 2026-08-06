<?php

declare(strict_types=1);

namespace App\Tests\Functional\Performance;

use App\Identity\Entity\User;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use App\Tests\Support\DatabaseWebTestCase;
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
        self::assertCount(10, $crawler->filter('.performance-table table tbody tr'));
        self::assertSelectorExists('.table-pagination');
        self::assertSelectorExists('a.table-pagination__link[href*="page=2"]');

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance?per_page=10&page=2');
        self::assertCount(2, $crawler->filter('.performance-table table tbody tr'));
    }

    public function testNPlusOneFilterShowsOnlyFlaggedTransactions(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('nplus1-perf@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $clean = $this->makeTransaction($project, 'GET /clean', new DateTimeImmutable('-1 hour'));
        $clean->setNPlusOneCount(0);
        $flagged = $this->makeTransaction($project, 'GET /nplus1', new DateTimeImmutable('-2 hours'));
        $flagged->setNPlusOneCount(6);
        $em->persist($clean);
        $em->persist($flagged);
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/performance?nplus1=1',
        );
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('.performance-table table tbody tr');
        self::assertGreaterThanOrEqual(1, $rows->count());
        self::assertStringContainsString('GET /nplus1', $crawler->filter('.performance-table')->text());
        self::assertStringNotContainsString('GET /clean', $crawler->filter('.performance-table table tbody')->text());
    }

    public function testPerformanceListShowsTransactionNamesWithoutNPlusOneFilter(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('list-perf@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->persist($this->makeTransaction($project, 'GET /alpha', new DateTimeImmutable('-1 hour')));
        $em->persist($this->makeTransaction($project, 'POST /beta', new DateTimeImmutable('-2 hours')));
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance');
        self::assertResponseIsSuccessful();
        $text = $crawler->filter('.performance-table')->text();
        self::assertStringContainsString('GET /alpha', $text);
        self::assertStringContainsString('POST /beta', $text);
    }

    public function testPerformancePaginationClampsPageBeyondLast(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('page-edge-perf@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $em->createQueryBuilder()
            ->delete(PerfTransaction::class, 't')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->execute();

        for ($i = 1; $i <= 12; ++$i) {
            $em->persist($this->makeTransaction(
                $project,
                \sprintf('GET /edge-%02d', $i),
                new DateTimeImmutable(\sprintf('-%d hours', 13 - $i)),
            ));
        }
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/performance?per_page=10&page=99',
        );
        self::assertResponseIsSuccessful();
        // ALLOWED_PER_PAGE includes 10; page 99 clamps to last page (2 rows).
        self::assertCount(2, $crawler->filter('.performance-table table tbody tr'));
        self::assertSelectorExists('.table-pagination');
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
