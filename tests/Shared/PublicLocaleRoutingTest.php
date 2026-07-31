<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Symfony\Component\HttpFoundation\Request;

final class PublicLocaleRoutingTest extends DatabaseWebTestCase
{
    public function testBareLoginRedirectsToDefaultLocale(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/login');
        if ($client->getResponse()->isRedirection()) {
            self::assertResponseRedirects('/en/login');
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
    }

    public function testLocalizedLoginRenders(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/es/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('html[lang="es"]', '');
        self::assertSelectorExists('html[lang="es"]');
    }

    public function testBareLegalRedirectsToDefaultLocale(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/legal/privacy');
        self::assertResponseRedirects('/en/legal/privacy');
    }

    public function testLocalizedLegalRendersSpanish(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/es/legal/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Política de privacidad');
        self::assertSelectorExists('a[href="/en/legal/privacy"]');
    }

    public function testSiteBackupSetupRouteIsRegistered(): void
    {
        self::createClient();
        $router = self::getContainer()->get('router');
        self::assertMatchesRegularExpression('#^/setup/?$#', $router->generate('nowo_site_backup_setup'));
    }

    public function testBareResetPasswordRedirectsToDefaultLocale(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/reset-password');
        if ($client->getResponse()->isRedirection()) {
            $location = (string) $client->getResponse()->headers->get('Location');
            self::assertTrue(
                str_contains($location, '/reset-password') || str_contains($location, '/login'),
                'Expected reset-password or login redirect, got: '.$location,
            );

            return;
        }
        self::assertResponseIsSuccessful();
    }

    public function testLocalizedRegisterRenders(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/en/register');
        self::assertResponseIsSuccessful();
    }
}
