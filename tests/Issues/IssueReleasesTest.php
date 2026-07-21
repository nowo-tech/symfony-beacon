<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\ProjectApiKey;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class IssueReleasesTest extends DatabaseWebTestCase
{
    public function testIngestSetsReleaseFieldsAndFilterShowsNewInReleaseBadge(): void
    {
        [$client, $user, $project, $apiKey] = $this->bootWithDemoProject('releases@example.com');

        $this->ingestEvent($client, $project->getId() ?? 0, $apiKey, 'Release filter error A', '1.0.0', 'production');
        $this->ingestEvent($client, $project->getId() ?? 0, $apiKey, 'Release filter error B', '2.0.0', 'staging');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var IssueRepository $issues */
        $issues = $em->getRepository(Issue::class);
        $issueA = $issues->findOneBy(['title' => 'LogicException: Release filter error A']);
        $issueB = $issues->findOneBy(['title' => 'LogicException: Release filter error B']);
        self::assertInstanceOf(Issue::class, $issueA);
        self::assertInstanceOf(Issue::class, $issueB);
        self::assertSame('1.0.0', $issueA->getFirstRelease());
        self::assertSame('1.0.0', $issueA->getLastRelease());
        self::assertSame('production', $issueA->getLastEnvironment());
        self::assertSame('2.0.0', $issueB->getFirstRelease());

        // Later event on A updates last release but not first.
        $this->ingestEvent($client, $project->getId() ?? 0, $apiKey, 'Release filter error A', '1.1.0', 'production');
        $em->clear();
        $issueA = $issues->find($issueA->getId());
        self::assertInstanceOf(Issue::class, $issueA);
        self::assertSame('1.0.0', $issueA->getFirstRelease());
        self::assertSame('1.1.0', $issueA->getLastRelease());

        $this->login($client, $user);
        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?release=1.0.0&status=',
        );
        self::assertResponseIsSuccessful();
        $body = $crawler->text();
        self::assertStringContainsString('Release filter error A', $body);
        self::assertStringNotContainsString('Release filter error B', $body);
        self::assertGreaterThan(0, $crawler->filter('[data-testid="new-in-release"]')->count());
        self::assertStringContainsString('New in release', $crawler->filter('[data-testid="new-in-release"]')->text());
    }

    public function testReleaseFilterAndEnvironmentCompare(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('releases-compare@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $onlyProd = new Issue();
        $onlyProd->setProject($project);
        $onlyProd->setFingerprint(hash('sha256', 'only-prod'));
        $onlyProd->setTitle('Only production');
        $onlyProd->setCulprit('a.php');
        $onlyProd->setLevel('error');
        $onlyProd->setFirstRelease('3.0.0');
        $onlyProd->setLastRelease('3.0.0');
        $onlyProd->setLastEnvironment('production');

        $onlyStaging = new Issue();
        $onlyStaging->setProject($project);
        $onlyStaging->setFingerprint(hash('sha256', 'only-staging'));
        $onlyStaging->setTitle('Only staging');
        $onlyStaging->setCulprit('b.php');
        $onlyStaging->setLevel('error');
        $onlyStaging->setFirstRelease('3.0.0');
        $onlyStaging->setLastRelease('3.0.0');
        $onlyStaging->setLastEnvironment('staging');

        $otherRelease = new Issue();
        $otherRelease->setProject($project);
        $otherRelease->setFingerprint(hash('sha256', 'other-rel'));
        $otherRelease->setTitle('Other release issue');
        $otherRelease->setCulprit('c.php');
        $otherRelease->setLevel('error');
        $otherRelease->setFirstRelease('2.0.0');
        $otherRelease->setLastRelease('2.0.0');
        $otherRelease->setLastEnvironment('production');

        $em->persist($onlyProd);
        $em->persist($onlyStaging);
        $em->persist($otherRelease);
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?release=3.0.0&status=',
        );
        self::assertResponseIsSuccessful();
        $body = $crawler->text();
        self::assertStringContainsString('Only production', $body);
        self::assertStringContainsString('Only staging', $body);
        self::assertStringNotContainsString('Other release issue', $body);
        self::assertGreaterThan(0, $crawler->filter('[data-testid="new-in-release"]')->count());

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues?environment=production&compare=staging&status=',
        );
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="issue-compare"]');
        $compareText = $crawler->filter('[data-testid="issue-compare"]')->text();
        self::assertStringContainsString('Only production', $compareText);
        self::assertStringContainsString('Only staging', $compareText);
        self::assertStringContainsString('Other release issue', $compareText);
    }

    private function ingestEvent(
        KernelBrowser $client,
        int $projectId,
        ProjectApiKey $apiKey,
        string $message,
        string $release,
        string $environment,
    ): void {
        $eventId = bin2hex(random_bytes(16));
        $body = implode("\n", [
            json_encode(['event_id' => $eventId], \JSON_THROW_ON_ERROR),
            json_encode(['type' => 'event'], \JSON_THROW_ON_ERROR),
            json_encode([
                'event_id' => $eventId,
                'message' => $message,
                'level' => 'error',
                'release' => $release,
                'environment' => $environment,
                'exception' => [
                    'values' => [[
                        'type' => 'LogicException',
                        'value' => $message,
                        'stacktrace' => ['frames' => [['filename' => 'x.php', 'function' => 'f', 'lineno' => 1]]],
                    ]],
                ],
            ], \JSON_THROW_ON_ERROR),
        ]);

        $client->request(
            Request::METHOD_POST,
            '/api/'.$projectId.'/envelope/',
            [],
            [],
            $this->beaconAuthHeaders($apiKey),
            $body,
        );
        self::assertResponseIsSuccessful();
    }
}
