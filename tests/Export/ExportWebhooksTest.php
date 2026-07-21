<?php

declare(strict_types=1);

namespace App\Tests\Export;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Shared\IssueStatus;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExportWebhooksTest extends DatabaseWebTestCase
{
    public function testOwnerExportsFilteredIssuesCsvAndJson(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('export-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $keep = $this->persistIssue($em, $project, 'export-keep', 'Keep me', 'error', IssueStatus::Unresolved);
        $this->persistIssue($em, $project, 'export-drop', 'Drop me', 'warning', IssueStatus::Unresolved);
        $em->flush();

        $this->login($client, $owner);

        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/export/issues.json?level=error&status=unresolved',
        );
        self::assertResponseIsSuccessful();
        /** @var array{count: int, issues: list<array{uuid: string, title: string}>} $json */
        $json = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $json['count']);
        self::assertSame($keep->getUuid(), $json['issues'][0]['uuid']);
        self::assertSame('Keep me', $json['issues'][0]['title']);

        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/export/issues.csv?level=error&status=unresolved',
        );
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $client->getResponse()->headers->get('Content-Type'));
        $csv = $this->streamedContent($client);
        self::assertStringContainsString('Keep me', $csv);
        self::assertStringNotContainsString('Drop me', $csv);
        self::assertStringContainsString($keep->getUuid(), $csv);
    }

    public function testMemberCannotExport(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('export-deny-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('export-member@example.com');
        $member->setDisplayName('Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();

        $this->login($client, $member);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/export/issues.json');
        self::assertResponseStatusCodeSame(403);

        // silence unused
        self::assertNotNull($owner->getId());
    }

    public function testEventsExportJsonEmptyAndWithRows(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('export-events@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/export/events.json');
        self::assertResponseIsSuccessful();
        /** @var array{count: int, events: list<mixed>} $empty */
        $empty = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(0, $empty['count']);
        self::assertSame([], $empty['events']);

        $issue = $this->persistIssue($em, $project, 'export-evt-issue', 'With event', 'error', IssueStatus::Unresolved);
        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId('evt-export-1');
        $event->setEnvironment('prod');
        $event->setReleaseVersion('1.2.3');
        $event->setPlatform('php');
        $event->setPayload(['message' => 'secret-should-not-export']);
        $em->persist($event);
        $em->flush();

        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/export/events.json?environment=prod',
        );
        self::assertResponseIsSuccessful();
        /** @var array{count: int, events: list<array{event_id: string, issue_uuid: string}>} $json */
        $json = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $json['count']);
        self::assertSame('evt-export-1', $json['events'][0]['event_id']);
        self::assertSame($issue->getUuid(), $json['events'][0]['issue_uuid']);
        self::assertStringNotContainsString('secret-should-not-export', (string) $client->getResponse()->getContent());
    }

    public function testLifecycleWebhooksOnResolveAssignCommentDuplicate(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('lifecycle-hooks@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('lifecycle-assignee@example.com');
        $member->setDisplayName('Assignee');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Lifecycle HTTP');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/lifecycle');
        $destination->setEnabled(true);
        $destination->setCategories([
            NotificationCategories::ISSUE_RESOLVED,
            NotificationCategories::ISSUE_ASSIGNED,
            NotificationCategories::ISSUE_COMMENTED,
            NotificationCategories::ISSUE_DUPLICATED,
        ]);
        $project->addNotificationDestination($destination);

        $canonical = $this->persistIssue($em, $project, 'life-canonical', 'Canonical', 'error', IssueStatus::Unresolved);
        $issue = $this->persistIssue($em, $project, 'life-target', 'Lifecycle target', 'error', IssueStatus::Unresolved);

        $em->persist($member);
        $em->persist($destination);
        $em->flush();

        $client->disableReboot();

        $requests = [];
        $mock = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return new MockResponse('ok', ['http_code' => 200]);
        });
        self::getContainer()->set(HttpClientInterface::class, $mock);

        $this->login($client, $owner);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(),
        );
        $resolveForm = $crawler->filter('form.issue-status-actions__form')->reduce(
            static fn ($node): bool => str_contains((string) $node->html(), 'value="resolved"')
        )->form();
        $client->submit($resolveForm);
        self::assertResponseRedirects();
        self::assertGreaterThanOrEqual(1, \count($requests));
        self::assertStringContainsString('issue.resolved', (string) $requests[\count($requests) - 1]['body']);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(),
        );
        $form = $crawler->filter('form.issue-assignee-form')->form();
        $assigneeField = $form->get('issue_assignee[assignee]');
        self::assertInstanceOf(ChoiceFormField::class, $assigneeField);
        $assigneeField->disableValidation();
        $assigneeField->setValue((string) $member->getId());
        $client->submit($form);
        self::assertResponseRedirects();
        self::assertStringContainsString('issue.assigned', (string) $requests[\count($requests) - 1]['body']);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(),
        );
        $commentToken = $crawler->filter('form.issue-comments__form input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/comments', [
            '_token' => $commentToken,
            'body' => 'Webhook comment body',
        ]);
        self::assertResponseRedirects();
        self::assertStringContainsString('issue.commented', (string) $requests[\count($requests) - 1]['body']);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(),
        );
        $dupToken = $crawler->filter('[data-testid="mark-duplicate"] form.confirm-dialog__panel input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/duplicate', [
            '_token' => $dupToken,
            'canonical_uuid' => $canonical->getUuid(),
        ]);
        self::assertResponseRedirects();
        self::assertStringContainsString('issue.duplicated', (string) $requests[\count($requests) - 1]['body']);
        self::assertStringContainsString($canonical->getUuid(), (string) $requests[\count($requests) - 1]['body']);
    }

    private function streamedContent(KernelBrowser $client): string
    {
        $internal = $client->getInternalResponse();
        $content = $internal->getContent();
        if ('' !== $content) {
            return $content;
        }

        $response = $client->getResponse();
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    private function persistIssue(
        EntityManagerInterface $em,
        Project $project,
        string $fingerprintSeed,
        string $title,
        string $level,
        IssueStatus $status,
    ): Issue {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $fingerprintSeed));
        $issue->setTitle($title);
        $issue->setCulprit('demo');
        $issue->setLevel($level);
        $issue->setStatus($status);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        $em->persist($issue);

        return $issue;
    }
}
