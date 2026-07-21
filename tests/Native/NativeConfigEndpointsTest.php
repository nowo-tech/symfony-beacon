<?php

declare(strict_types=1);

namespace App\Tests\Native;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Hotwire Native bridge endpoints and layout were removed from Beacon.
 * Keep a smoke check so accidental reintroduction of /config/* routes is noticed,
 * and that a former Native UA still gets a normal login page.
 */
final class NativeConfigEndpointsTest extends WebTestCase
{
    public function testIosConfigRouteIsGone(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/config/ios_v1.json');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAndroidConfigRouteIsGone(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/config/android_v1.json');
        self::assertResponseStatusCodeSame(404);
    }

    public function testHotwireNativeUserAgentGetsNormalLoginShell(): void
    {
        $client = self::createClient();
        $client->request(
            Request::METHOD_GET,
            '/en/login',
            server: ['HTTP_USER_AGENT' => 'Hotwire Native iOS; BeaconDemo'],
        );
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('page-shell--native', $client->getResponse()->getContent() ?: '');
    }
}
