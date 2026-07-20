<?php

declare(strict_types=1);

namespace App\Tests\Native;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class NativeConfigEndpointsTest extends WebTestCase
{
    public function testIosConfigIsPublicJson(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/config/ios_v1.json');
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('rules', $payload);
        self::assertNotEmpty($payload['rules']);
    }

    public function testAndroidConfigIsPublicJson(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/config/android_v1.json');
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('rules', $payload);
    }

    public function testNativeUserAgentMarksLayout(): void
    {
        $client = self::createClient();
        $client->request(
            Request::METHOD_GET,
            '/en/login',
            server: ['HTTP_USER_AGENT' => 'Hotwire Native iOS; BeaconDemo'],
        );
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('page-shell--native', $client->getResponse()->getContent() ?: '');
        self::assertStringNotContainsString('nowo_pwa_install', $client->getResponse()->getContent() ?: '');
    }
}
