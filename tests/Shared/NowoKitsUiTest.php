<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Issues\Entity\Issue;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use DateTimeImmutable;
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
        $menuSeeder->seedIfEmpty();
        $breadcrumbSeeder->seedIfEmpty();

        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-app-shell]');
        self::assertSelectorExists('#app-sidebar');
        self::assertSelectorExists('#dashboard-menu-navigation');
        self::assertSelectorTextContains('.app-sidebar__label', 'Dashboard');
        self::assertSelectorTextContains('.beacon-nav', 'Projects');
        self::assertSelectorNotExists('.beacon-nav a[href="/projects/new"]');
        self::assertSelectorTextContains('.beacon-nav', 'API docs');
        self::assertSelectorExists('button[data-action="confirm-dialog#open"]');
        self::assertSelectorExists('dialog.confirm-dialog');
        self::assertSelectorExists('.user-avatar');
        self::assertSelectorTextContains('.user-menu', 'Preferences');
        self::assertSelectorTextContains('.user-menu', 'Dashboard');
        self::assertSelectorNotExists('.user-menu a[href="/admin"]');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid());
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues');
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

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.beacon-breadcrumb-wrap');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Projects');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Project');
        self::assertSelectorTextContains('.beacon-breadcrumb-wrap', 'Settings');
    }

    public function testIssueShowBreadcrumbsUseProjectIdNotIssueId(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('issue-bc@example.com');
        self::getContainer()->get(BreadcrumbDemoSeeder::class)->seedIfEmpty();

        $em = self::getContainer()->get('doctrine')->getManager();

        // Ensure issue id ≠ project id so a wrong param copy is detectable.
        $padding = new Issue();
        $padding->setProject($project);
        $padding->setFingerprint(hash('sha256', 'breadcrumb-pad'));
        $padding->setTitle('Padding issue');
        $padding->setCulprit('demo');
        $padding->setLevel('error');
        $padding->setFirstSeen(new DateTimeImmutable());
        $padding->setLastSeen(new DateTimeImmutable());
        $padding->incrementEventCount();
        $em->persist($padding);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'breadcrumb-issue'));
        $issue->setTitle('Breadcrumb issue');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        $em->persist($issue);
        $em->flush();

        $projectUuid = $project->getUuid();
        $issueUuid = $issue->getUuid();
        self::assertNotSame($projectUuid, $issueUuid);

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$projectUuid.'/issues/'.$issueUuid);
        self::assertResponseIsSuccessful();

        $wrap = $crawler->filter('.beacon-breadcrumb-wrap');
        self::assertGreaterThan(0, $wrap->count());
        $html = $wrap->html();
        self::assertStringContainsString('/projects/'.$projectUuid.'/issues"', $html);
        self::assertStringContainsString('/projects/'.$projectUuid.'"', $html);
        self::assertStringNotContainsString('/projects/'.$issueUuid.'"', $html);
        self::assertStringNotContainsString('/projects/'.$issueUuid.'/issues', $html);
    }

    public function testPreferencesSectionUsesPreferencesMenu(): void
    {
        [$client, $user] = $this->bootWithDemoProject();
        $menuSeeder = self::getContainer()->get(DashboardMenuDemoSeeder::class);
        $menuSeeder->seedIfEmpty();
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
        $menuSeeder->seedIfEmpty();

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
