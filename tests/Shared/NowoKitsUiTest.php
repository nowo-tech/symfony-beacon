<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies section shell: avatar switches areas, each with its own sidebar menu.
 */
final class NowoKitsUiTest extends DatabaseWebTestCase
{
    public function testDashboardSectionSidebarAndBreadcrumbs(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject();

        $menuSeeder = self::getContainer()->get(DashboardMenuDemoSeeder::class);
        $breadcrumbSeeder = self::getContainer()->get(BreadcrumbDemoSeeder::class);
        self::assertTrue($menuSeeder->seedIfEmpty());
        self::assertTrue($breadcrumbSeeder->seedIfEmpty());

        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-app-shell]');
        self::assertSelectorExists('#app-sidebar');
        self::assertSelectorExists('#dashboard-menu-navigation');
        self::assertSelectorTextContains('.app-sidebar__label', 'Dashboard');
        self::assertSelectorTextContains('.beacon-nav', 'Projects');
        self::assertSelectorTextContains('.beacon-nav', 'New project');
        self::assertSelectorExists('.user-avatar');
        self::assertSelectorTextContains('.user-menu', 'Preferences');
        self::assertSelectorTextContains('.user-menu', 'Dashboard');
        self::assertSelectorNotExists('.user-menu a[href="/admin"]');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getId());
        self::assertResponseRedirects('/projects/'.$project->getId().'/issues');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#dashboard-menu-navigation');
        self::assertSelectorExists('.beacon-breadcrumb-wrap');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Projects');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Issues');
    }

    public function testProjectSettingsShowsBreadcrumbTrail(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('settings-bc@example.com');
        self::getContainer()->get(BreadcrumbDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getId().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.beacon-breadcrumb-wrap');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Projects');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Project');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Settings');
    }

    public function testPreferencesSectionUsesPreferencesMenu(): void
    {
        [$client, $user] = $this->bootWithDemoProject();
        $menuSeeder = self::getContainer()->get(DashboardMenuDemoSeeder::class);
        self::assertTrue($menuSeeder->seedIfEmpty());
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#preferences-menu-navigation');
        self::assertSelectorTextContains('.app-sidebar__label', 'Preferences');
        self::assertSelectorTextContains('.beacon-nav', 'Profile');
        self::assertSelectorTextContains('.beacon-nav', 'Security');
        self::assertSelectorTextContains('.beacon-nav', 'Display');
    }

    public function testAdminSectionSidebarForAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-shell@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $user->setRoles(['ROLE_ADMIN']);
        $user->setDisplayName('Demo Admin');
        $em->flush();

        $menuSeeder = self::getContainer()->get(DashboardMenuDemoSeeder::class);
        self::assertTrue($menuSeeder->seedIfEmpty());

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#administration-menu-navigation');
        self::assertSelectorTextContains('.app-sidebar__label', 'Administration');
        self::assertSelectorTextContains('.beacon-nav', 'Users');
        self::assertSelectorTextContains('.beacon-nav', 'Appearance');
        self::assertSelectorTextContains('.beacon-nav', 'Menus');
        self::assertSelectorTextContains('.beacon-nav', 'Breadcrumbs');
        self::assertSelectorTextContains('.user-menu', 'Administration');
        self::assertSelectorTextContains('.user-avatar', 'DA');
    }

    public function testKitAdminDashboardsRequireAuth(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/admin/menus/');
        self::assertResponseRedirects('/en/login');

        $client->request(Request::METHOD_GET, '/breadcrumb-kit-admin/collections');
        self::assertResponseRedirects('/en/login');
    }

    public function testKitAdminDashboardsAccessibleWhenLoggedIn(): void
    {
        [$client, $user] = $this->bootWithDemoProject();
        $user->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/menus/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-app-shell]');
        self::assertSelectorExists('#administration-menu-navigation');
        self::assertSelectorExists('.kit-admin');
        self::assertSelectorNotExists('.navbar.bg-dark');
        $menusHtml = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('--bs-btn-bg: var(--beacon-moss)', $menusHtml);
        self::assertStringContainsString('btn-outline-primary', $menusHtml);

        $client->request(Request::METHOD_GET, '/breadcrumb-kit-admin/collections');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-app-shell]');
        self::assertSelectorExists('.kit-admin');
        self::assertSelectorNotExists('.navbar.bg-dark');
        $crumbsHtml = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('--bs-btn-bg: var(--beacon-moss)', $crumbsHtml);
    }

    public function testMenuShowHasAdminBreadcrumbTrail(): void
    {
        [$client, $user] = $this->bootWithDemoProject('menu-bc@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        self::getContainer()->get(BreadcrumbDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/menus/');
        self::assertResponseIsSuccessful();
        $href = null;
        foreach ($client->getCrawler()->filter('a[href]')->links() as $link) {
            $uri = $link->getUri();
            if (preg_match('#/admin/menus/\d+$#', parse_url($uri, \PHP_URL_PATH) ?: '')) {
                $href = parse_url($uri, \PHP_URL_PATH);
                break;
            }
        }
        self::assertNotNull($href);

        $client->request(Request::METHOD_GET, (string) $href);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.beacon-breadcrumb-wrap');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Administration');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Menus');
        self::assertSelectorExists('.kit-admin');
        self::assertSelectorNotExists('.btn-outline-info');
    }
}
