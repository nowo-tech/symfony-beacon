<?php

declare(strict_types=1);

namespace App\Tests\Functional\Native;

use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Hotwire Native is deferred (roadmap Later / specs/008-ux-native).
 * Smoke checks: /config/* must stay gone, and a former Native UA keeps a normal login page.
 */
final class NativeConfigEndpointsTest extends DatabaseWebTestCase
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
