<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\Demo\DemoFixtureLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Keeps breadcrumb fixture coverage for shell pages that redirect from an index route
 * onto a *_section / child route (otherwise the trail is empty in the UI).
 */
final class BreadcrumbFixtureCoverageTest extends TestCase
{
    /**
     * Authenticated HTML pages that must resolve a breadcrumb item by route name.
     *
     * @return list<string>
     */
    private function requiredPageRoutes(): array
    {
        return [
            'admin_hub',
            'admin_appearance',
            // Index redirects here — without this row, /admin/appearance/{section} has no trail.
            'admin_appearance_section',
            'admin_ops_defaults',
            'admin_ops_defaults_section',
            'admin_mailer',
            'admin_mercure',
            'admin_instance_config',
            'admin_projects',
            'admin_projects_show',
            'admin_users',
            'admin_groups',
            'nowo_routing_kit_panel',
            'nowo_routing_kit_panel_create',
            'nowo_routing_kit_panel_edit',
            // Site Backup panel/history use guest_shell (password gate) — no app.user crumbs.
            // RoutingKit conflicts is JSON, not an HTML page.
            'nowo_site_backup_setup',
            'nowo_http_log_admin_index',
            'nowo_http_log_admin_show',
            'nowo_dashboard_menu_dashboard_index',
            'nowo_breadcrumb_kit_dashboard_collections_index',
            'account_profile',
            'dashboard_home',
            'project_settings',
            'issue_index',
        ];
    }

    #[Test]
    public function defaultBreadcrumbFixtureCoversRequiredPageRoutes(): void
    {
        $data = new DemoFixtureLoader()->load('breadcrumbs.default.json');
        self::assertIsArray($data['items'] ?? null);

        $routes = [];
        foreach ($data['items'] as $item) {
            self::assertIsArray($item);
            self::assertIsString($item['route'] ?? null);
            $routes[] = $item['route'];
        }

        $missing = array_values(array_diff($this->requiredPageRoutes(), $routes));
        self::assertSame([], $missing, 'Add missing routes to breadcrumbs.default.json');
    }

    #[Test]
    public function appearanceSectionMirrorsOpsDefaultsPattern(): void
    {
        $data = new DemoFixtureLoader()->load('breadcrumbs.default.json');
        $byRoute = [];
        foreach ($data['items'] as $item) {
            $byRoute[$item['route']] = $item;
        }

        self::assertSame('admin_hub', $byRoute['admin_appearance_section']['parent']);
        self::assertSame(['section'], $byRoute['admin_appearance_section']['dynamicParamKeys']);
        self::assertSame('admin_hub', $byRoute['admin_ops_defaults_section']['parent']);
        self::assertSame(['section'], $byRoute['admin_ops_defaults_section']['dynamicParamKeys']);
    }
}
