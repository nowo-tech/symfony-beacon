<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Project\Entity\Project;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NotificationSampleSendTest extends DatabaseWebTestCase
{
    public function testForTestPayloadIsChannelAwareWithSampleIssue(): void
    {
        [, , $project] = $this->bootWithDemoProject('sample-payload@example.com');
        $builder = new NotificationPayloadBuilder(self::getContainer()->get(UrlGeneratorInterface::class));

        $slack = $builder->forTest($project, 'Ops', NotificationDestinationType::Slack);
        self::assertTrue($slack['test']);
        self::assertSame('test', $slack['event']);
        self::assertSame('slack', $slack['channel']);
        self::assertStringContainsString('[TEST] Slack sample', (string) $slack['summary']);
        self::assertIsArray($slack['issue']);
        self::assertSame('error', $slack['issue']['level']);
    }

    public function testOwnerCanQueueSampleSendForDisabledDiscordDestination(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('sample-send@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Discord ops');
        $destination->setType(NotificationDestinationType::Discord);
        $destination->setEndpointUrl('https://discord.com/api/webhooks/1/sample-token');
        $destination->setEnabled(false);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $requests = [];
        $mock = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => $options['body'] ?? '',
            ];

            return new MockResponse('ok', ['http_code' => 204]);
        });
        self::getContainer()->set(HttpClientInterface::class, $mock);

        // Exercise delivery path directly (sync messenger in test) so the mock replaces HttpClient
        // before DeliverNotificationHandler is constructed.
        $dispatcher = self::getContainer()->get(\App\Notifications\Service\NotificationDispatcher::class);
        $dispatcher->dispatchTest($project, $destination);

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertStringContainsString('discord.com', $requests[0]['url']);
        self::assertStringContainsString('[TEST] Discord sample', (string) $requests[0]['body']);
        self::assertStringContainsString('embeds', (string) $requests[0]['body']);

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/test"]');
    }
}
