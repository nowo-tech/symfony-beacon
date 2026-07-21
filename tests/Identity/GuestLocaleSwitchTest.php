<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class GuestLocaleSwitchTest extends DatabaseWebTestCase
{
    public function testLocaleSwitcherUsesPathLocaleOnLogin(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/es/login"]');

        $client->request(Request::METHOD_GET, '/es/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.locale-switcher__code', 'ES');
        self::assertSelectorExists('html[lang="es"]');
    }

    public function testAnonymousLegalPagesShowLocaleAndThemeSwitchers(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/en/legal/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-theme-toggle]');
        self::assertSelectorExists('.locale-switcher');
        self::assertSelectorExists('a[href="/es/legal/privacy"]');
    }

    public function testGuestLocaleHelperLocalizesBarePublicRedirect(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/locale/es', ['redirect' => '/legal/privacy']);
        self::assertResponseRedirects('/es/legal/privacy');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.locale-switcher__code', 'ES');
        self::assertSelectorTextContains('h1', 'Política de privacidad');
    }
}
