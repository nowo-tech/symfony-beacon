<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notifications;

use App\Notifications\Realtime\IssueRealtimeTopics;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class MemberRealtimeFunctionalTest extends DatabaseWebTestCase
{
    public function testGuestCannotFetchRealtimeConfig(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/account/realtime/config');
        self::assertResponseRedirects();
    }

    public function testMemberReceivesMercureTopicsForAccessibleProjects(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('realtime-config@example.com');

        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->setMercureEnabled(true);
        $settings->setMercureUrl('http://mercure/.well-known/mercure');
        $settings->setMercurePublicUrl('https://beacon.test/.well-known/mercure');
        $settings->setMercureJwtSecret('!ChangeThisMercureHubJWTSecretKey!');
        self::getContainer()->get(InstanceSettingsRepository::class)->save($settings);
        self::getContainer()->get(ConfiguredMercure::class)->reset();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/account/realtime/config');
        self::assertResponseIsSuccessful();

        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($payload);
        self::assertTrue($payload['mercure']['enabled']);
        // Same-origin hub URL for EventSource + CSP connect-src 'self'.
        self::assertSame('http://localhost/.well-known/mercure', $payload['mercure']['hubUrl']);
        self::assertContains(IssueRealtimeTopics::forUser($user->getUuid()), $payload['mercure']['topics']);
        self::assertSame([IssueRealtimeTopics::forUser($user->getUuid())], $payload['mercure']['topics']);
        self::assertIsString($payload['mercure']['token']);
        self::assertNotSame('', $payload['mercure']['token']);
        self::assertFalse($payload['push']['preferenceEnabled']);
    }

    public function testPushSubscribeRejectsInvalidCsrfAndDisabledPreference(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('realtime-push@example.com');
        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        $csrf = $crawler->filter('[data-issue-realtime-csrf-token-value]')->attr('data-issue-realtime-csrf-token-value');

        $client->request(
            Request::METHOD_POST,
            '/account/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => 'invalid'],
            content: '{"endpoint":"https://fcm.googleapis.com/fcm/send/abc","keys":{"p256dh":"key","auth":"auth"}}',
        );
        self::assertResponseStatusCodeSame(403);

        $client->request(
            Request::METHOD_POST,
            '/account/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: '{"endpoint":"https://fcm.googleapis.com/fcm/send/abc","keys":{"p256dh":"key","auth":"auth"}}',
        );
        self::assertResponseStatusCodeSame(400);
        self::assertSame('preference_disabled', json_decode($client->getResponse()->getContent() ?: '', true)['error'] ?? null);
    }

    public function testPushUnsubscribeAcceptsValidCsrfAndClearsSubscriptions(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('realtime-unsub@example.com');
        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        $csrf = $crawler->filter('[data-issue-realtime-csrf-token-value]')->attr('data-issue-realtime-csrf-token-value');

        $client->request(
            Request::METHOD_POST,
            '/account/push/unsubscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: '{}',
        );
        self::assertResponseIsSuccessful();
        self::assertSame(['ok' => true], json_decode($client->getResponse()->getContent() ?: '', true));
        self::assertSame([], self::getContainer()->get(PushSubscriptionRepository::class)->findByUser($user));
    }

    public function testPushSubscribeHappyPathAndValidationErrorsWhenConfigured(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('realtime-push-ok@example.com');
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        self::getContainer()->set(
            \App\Notifications\Service\WebPushClientFactory::class,
            new \App\Notifications\Service\WebPushClientFactory(
                $keys['publicKey'],
                $keys['privateKey'],
                'mailto:ops@example.com',
            ),
        );

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user->setPushNotificationsEnabled(true);
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();
        $csrf = $crawler->filter('[data-issue-realtime-csrf-token-value]')->attr('data-issue-realtime-csrf-token-value');

        $client->request(
            Request::METHOD_POST,
            '/account/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: 'not-json',
        );
        self::assertResponseStatusCodeSame(400);

        $client->request(
            Request::METHOD_POST,
            '/account/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: '{"endpoint":"","keys":{"p256dh":"k","auth":"a"}}',
        );
        self::assertResponseStatusCodeSame(400);

        $client->request(
            Request::METHOD_POST,
            '/account/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: '{"endpoint":"https://fcm.googleapis.com/fcm/send/nokeys","keys":{"p256dh":"","auth":"a"}}',
        );
        self::assertResponseStatusCodeSame(400);

        $client->request(
            Request::METHOD_POST,
            '/account/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: '{"endpoint":"https://evil.example/push","keys":{"p256dh":"k","auth":"a"}}',
        );
        self::assertResponseStatusCodeSame(400);

        $client->request(
            Request::METHOD_POST,
            '/account/push/subscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: '{"endpoint":"https://fcm.googleapis.com/fcm/send/abc","keys":{"p256dh":"k","auth":"a"},"contentEncoding":""}',
        );
        self::assertResponseIsSuccessful();
        self::assertSame(['ok' => true], json_decode($client->getResponse()->getContent() ?: '', true));

        $client->request(Request::METHOD_GET, '/account/realtime/config');
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertTrue($payload['push']['preferenceEnabled']);

        $client->request(
            Request::METHOD_POST,
            '/account/push/unsubscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => 'bad'],
            content: '{}',
        );
        self::assertResponseStatusCodeSame(403);

        $client->request(
            Request::METHOD_POST,
            '/account/push/unsubscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: 'nope',
        );
        self::assertResponseStatusCodeSame(400);

        $client->request(
            Request::METHOD_POST,
            '/account/push/unsubscribe',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X-CSRF-TOKEN' => $csrf],
            content: '{"endpoint":"https://fcm.googleapis.com/fcm/send/abc"}',
        );
        self::assertResponseIsSuccessful();
    }

    public function testConfigHidesMercureWhenMemberAlertsDisabled(): void
    {
        [$client, $user] = $this->bootWithDemoProject('realtime-master-off@example.com');
        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->setMercureEnabled(true);
        $settings->setMercureUrl('http://mercure/.well-known/mercure');
        $settings->setMercurePublicUrl('https://beacon.test/.well-known/mercure');
        $settings->setMercureJwtSecret('!ChangeThisMercureHubJWTSecretKey!');
        self::getContainer()->get(InstanceSettingsRepository::class)->save($settings);
        self::getContainer()->get(ConfiguredMercure::class)->reset();

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user->setMemberAlertsEnabled(false);
        $em->flush();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/account/realtime/config');
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertFalse($payload['mercure']['enabled']);
        self::assertSame([], $payload['mercure']['topics']);
    }
}
