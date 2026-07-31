<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class GuestLocaleSwitchTest extends DatabaseWebTestCase
{
    public function testLocaleSwitcherUsesPathLocaleOnLogin(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        // Non-default locale keeps a prefix; DEFAULT_LOCALE (en in PHPUnit) uses bare /login.
        self::assertSelectorExists('a[href="/es/login"]');
        self::assertSelectorExists('a[href="/login"]');

        $client->request(Request::METHOD_GET, '/es/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.locale-switcher__code', 'ES');
        self::assertSelectorExists('html[lang="es"]');
        self::assertSelectorExists('a[href="/login"]');
    }

    public function testAnonymousLegalPagesShowLocaleAndThemeSwitchers(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/en/legal/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-theme-toggle]');
        self::assertSelectorExists('.locale-switcher');
        self::assertSelectorExists('a[href="/es/legal/privacy"]');
    }

    public function testGuestLocaleHelperRequiresPostAndLocalizesRedirect(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/locale/es', ['redirect' => '/legal/privacy']);
        self::assertResponseStatusCodeSame(405);

        $token = $this->guestLocaleCsrfToken($client);
        $client->request(Request::METHOD_POST, '/locale/es', [
            '_token' => $token,
            'redirect' => '/legal/privacy',
        ]);
        self::assertResponseRedirects('/es/legal/privacy');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.locale-switcher__code', 'ES');
        self::assertSelectorTextContains('h1', 'Política de privacidad');
    }

    public function testGuestLocaleRejectsOpenRedirectTargets(): void
    {
        $client = self::createClient();
        $token = $this->guestLocaleCsrfToken($client);

        $client->request(Request::METHOD_POST, '/locale/es', [
            '_token' => $token,
            'redirect' => '/\\evil.example',
        ]);
        self::assertResponseRedirects('/es/login');

        $token = $this->guestLocaleCsrfToken($client);
        $client->request(Request::METHOD_POST, '/locale/es', [
            '_token' => $token,
            'redirect' => '//evil.example',
        ]);
        self::assertResponseRedirects('/es/login');
    }

    /**
     * Login/legal pages use path-locale anchors, so CSRF must be minted against an
     * explicit session shared with the BrowserKit cookie jar.
     */
    private function guestLocaleCsrfToken(KernelBrowser $client): string
    {
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->start();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $request = Request::create('/');
        $request->setSession($session);
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        try {
            return self::getContainer()->get(CsrfTokenManagerInterface::class)
                ->getToken('guest_locale')
                ->getValue();
        } finally {
            $requestStack->pop();
            $session->save();
        }
    }
}
