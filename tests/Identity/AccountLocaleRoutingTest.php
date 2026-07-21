<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AccountLocaleRoutingTest extends DatabaseWebTestCase
{
    public function testAuthenticatedAppStripsLocaleQueryParam(): void
    {
        [$client, $user] = $this->bootWithDemoProject('locale-user@example.com');
        $user->setPreferredLocale('es');
        self::getContainer()->get('doctrine')->getManager()->flush();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/preferences?_locale=en');
        self::assertResponseRedirects('/account/preferences');
        $client->followRedirect();
        // /account/preferences redirects to /account/profile
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('_locale=', $client->getRequest()->getUri());
        self::assertSelectorTextContains('.locale-switcher__code', 'ES');
        self::assertSelectorTextContains('.app-sidebar__label', 'Preferencias');
        self::assertSelectorTextContains('h1', 'Perfil');
    }

    public function testLocaleSwitcherPostsPreferenceWithoutQueryLocale(): void
    {
        [$client, $user] = $this->bootWithDemoProject('locale-switch@example.com');
        $user->setPreferredLocale('en');
        self::getContainer()->get('doctrine')->getManager()->flush();
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/account/locale/es"]')->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringNotContainsString('_locale=', $location);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.locale-switcher__code', 'ES');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->getRepository($user::class)->find($user->getId());
        self::assertSame('es', $reloaded?->getPreferredLocale());
    }
}
