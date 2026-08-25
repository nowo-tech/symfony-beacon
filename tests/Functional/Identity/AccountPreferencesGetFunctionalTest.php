<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * GET smoke tests for {@see \App\Identity\Controller\AccountPreferencesController}.
 */
final class AccountPreferencesGetFunctionalTest extends DatabaseWebTestCase
{
    public function testPreferencesIndexRoutesRedirectToProfile(): void
    {
        [$client, $user] = $this->bootWithDemoProject('account-prefs-get@example.com');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account');
        self::assertResponseRedirects('/account/profile');

        $client->request(Request::METHOD_GET, '/account/preferences');
        self::assertResponseRedirects('/account/profile');
    }

    public function testAccountAreaGetPagesRenderForAuthenticatedUser(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('account-area-get@example.com');
        $user->setDisplayName('Prefs Getter');
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="profile-overview"]');
        self::assertSelectorTextContains('[data-testid="profile-overview"]', 'account-area-get@example.com');

        $client->request(Request::METHOD_GET, '/account/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="profile-projects"]');
        self::assertSelectorTextContains('[data-testid="profile-projects"]', $project->getName());

        $client->request(Request::METHOD_GET, '/account/groups');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="profile-groups"]');

        $client->request(Request::METHOD_GET, '/account/security/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="security-activity"]');
        self::assertSelectorTextContains('[data-testid="security-activity"]', 'No security events recorded yet');

        $client->request(Request::METHOD_GET, '/account/security/devices');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="trusted-devices"]');
        self::assertSelectorExists('[data-testid="trusted-devices-current"]');
        self::assertSelectorTextContains('[data-testid="trusted-devices-current"]', 'has not recognized');
        self::assertSelectorExists('[data-testid="trusted-devices-empty"]');
        self::assertSelectorTextContains('.preferences-nav', 'Trusted browsers');

        $client->request(Request::METHOD_GET, '/account/display/tours');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="user_preferences"]');
        self::assertSelectorTextContains('.preferences-nav', 'Tours');
    }

    public function testAccountGetRoutesRequireAuthentication(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/account/profile');
        self::assertTrue($client->getResponse()->isRedirection());
        self::assertMatchesRegularExpression('#/(en/)?login$#', (string) $client->getResponse()->headers->get('Location'));

        $client->request(Request::METHOD_GET, '/account/security/devices');
        self::assertTrue($client->getResponse()->isRedirection());
    }
}
