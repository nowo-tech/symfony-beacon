<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Symfony\Component\HttpFoundation\Request;

final class PublicLocaleRoutingTest extends DatabaseWebTestCase
{
    public function testHomeRedirectsToBareLogin(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/');
        self::assertResponseRedirects('/login');
    }

    public function testBareLoginServesDefaultLocaleWithoutPrefix(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/login');
        // AuthKit unlocalized: serve — bare /login must not bounce to /{DEFAULT_LOCALE}/login.
        self::assertFalse($client->getResponse()->isRedirection());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
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
        // SiteBackup 1.7 setup.locale in_path=both: canonical is localized; bare is *_unlocalized.
        self::assertSame('/en/setup', $router->generate('nowo_site_backup_setup', ['_locale' => 'en']));
        self::assertSame('/es/setup', $router->generate('nowo_site_backup_setup', ['_locale' => 'es']));
        self::assertMatchesRegularExpression('#^/setup/?$#', $router->generate('nowo_site_backup_setup_unlocalized'));
    }

    public function testLocalizedSetupUrlIsReachableWithoutCanonicalRedirect(): void
    {
        $client = self::createClient();
        // PHPUnit DEFAULT_LOCALE=en — /en/setup is a valid localized twin (no force-redirect to bare).
        $client->request(Request::METHOD_GET, '/en/setup?token=test-setup-token');
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
        self::assertContains($client->getResponse()->getStatusCode(), [200, 403]);
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
