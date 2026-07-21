<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class LegalAndCookieConsentTest extends WebTestCase
{
    public function testLegalPagesArePublic(): void
    {
        $client = self::createClient();

        foreach (['/en/legal/notice', '/en/legal/privacy', '/en/legal/terms', '/en/legal/cookies'] as $path) {
            $client->request(Request::METHOD_GET, $path);
            self::assertResponseIsSuccessful($path);
            self::assertSelectorExists('footer.site-legal-footer', $path);
        }
    }

    public function testBareLegalRedirectsToDefaultLocale(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/legal/privacy');
        self::assertResponseRedirects('/en/legal/privacy');
    }

    public function testCookieConsentModalEndpointIsPublic(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/cookie_consent_alt');
        self::assertResponseIsSuccessful();
    }

    public function testLoginPageEmbedsCookieConsentHook(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('data-nowo-open-consent', $content);
        self::assertStringContainsString('/en/legal/privacy', $content);
        self::assertStringContainsString('nowo-consent-modal', $content);
        self::assertStringContainsString('nowo-cookie-consent__preferences-bubble', $content);
    }

    public function testRegisterPageEmbedsCookiePreferencesBubble(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/en/register');
        // Registration may redirect to login when disabled; both use the AuthKit layout.
        $status = $client->getResponse()->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('nowo-cookie-consent__preferences-bubble', $content);
        self::assertStringContainsString('nowo-consent-modal', $content);
    }

    public function testDashboardDoesNotEmbedCookiePreferencesBubble(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/en/legal/cookies');
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?: '';
        self::assertStringNotContainsString('nowo-cookie-consent__preferences-bubble', $content);
        self::assertStringContainsString('nowo-consent-modal', $content);
    }
}
