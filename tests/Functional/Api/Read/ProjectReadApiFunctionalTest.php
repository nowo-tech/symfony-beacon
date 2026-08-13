<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Read;

use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Project\Service\ProjectReadTokenManager;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ProjectReadApiFunctionalTest extends DatabaseWebTestCase
{
    public function testUnauthorizedWithoutBearer(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('read-api-noauth@example.com');

        $client->request(Request::METHOD_GET, '/api/projects/'.$project->getUuid().'/issues');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('unauthorized', json_decode((string) $client->getResponse()->getContent(), true)['error'] ?? null);
    }

    public function testUnauthorizedWithIngestKeyAsBearer(): void
    {
        [$client, , $project, $apiKey] = $this->bootWithDemoProject('read-api-ingest@example.com');

        $client->request(
            Request::METHOD_GET,
            '/api/projects/'.$project->getUuid().'/issues',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$apiKey->getPublicKey()],
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testForbiddenWhenTokenBelongsToOtherProject(): void
    {
        [$client, $owner, $projectA] = $this->bootWithDemoProject('read-api-a@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $projectB = new Project();
        $projectB->setName('Other');
        $projectB->setSlug('other-'.bin2hex(random_bytes(4)));
        $em->persist($projectB);
        $em->flush();

        $created = self::getContainer()->get(ProjectReadTokenManager::class)->create($projectA, $owner, 'CI');
        $client->request(
            Request::METHOD_GET,
            '/api/projects/'.$projectB->getUuid().'/issues',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$created['rawToken']],
        );

        self::assertResponseStatusCodeSame(403);
        self::assertSame('forbidden', json_decode((string) $client->getResponse()->getContent(), true)['error'] ?? null);
    }

    public function testListsAndShowsIssuesWithReadToken(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('read-api-ok@example.com');
        $issue = $this->makeIssue($project, 'read-fp', 'Read API sample error');

        $created = self::getContainer()->get(ProjectReadTokenManager::class)->create($project, $owner, 'Export bot');
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer '.$created['rawToken']];

        $client->request(Request::METHOD_GET, '/api/projects/'.$project->getUuid().'/issues', [], [], $headers);
        self::assertResponseIsSuccessful();
        /** @var array<string, mixed> $list */
        $list = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($project->getUuid(), $list['project']['uuid'] ?? null);
        self::assertSame(1, $list['count'] ?? null);
        self::assertSame($issue->getUuid(), $list['issues'][0]['uuid'] ?? null);
        self::assertSame('Read API sample error', $list['issues'][0]['title'] ?? null);

        $client->request(
            Request::METHOD_GET,
            '/api/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(),
            [],
            [],
            $headers,
        );
        self::assertResponseIsSuccessful();
        /** @var array<string, mixed> $one */
        $one = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($issue->getUuid(), $one['issue']['uuid'] ?? null);
        self::assertSame('error', $one['issue']['level'] ?? null);
    }

    public function testRevokedTokenIsUnauthorized(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('read-api-revoked@example.com');
        $manager = self::getContainer()->get(ProjectReadTokenManager::class);
        $created = $manager->create($project, $owner, 'Temp');
        $manager->revoke($created['token'], $owner);

        $client->request(
            Request::METHOD_GET,
            '/api/projects/'.$project->getUuid().'/issues',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$created['rawToken']],
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testSettingsCreateShowsTokenOnce(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('read-api-settings@example.com');
        $this->login($client, $owner);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="read-api-tokens"]');

        $client->submitForm('Create token', [
            'project_read_token_create[label]' => 'Nightly export',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorExists('[data-testid="read-token-secret"]');
        $secret = $client->getCrawler()->filter('[data-testid="read-token-secret"]')->text();
        self::assertStringStartsWith('brt_', $secret);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertSelectorNotExists('[data-testid="read-token-secret"]');
        self::assertSelectorExists('[data-testid="read-token-row"]');
    }

    private function makeIssue(Project $project, string $fpSeed, string $title): Issue
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $fpSeed));
        $issue->setTitle($title);
        $issue->setCulprit('App\\ReadApi');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        $em->persist($issue);
        $em->flush();

        return $issue;
    }
}
