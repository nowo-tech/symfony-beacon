<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Twig;

use App\Shared\Menu\AdministrationMenuCurrentMarker;
use App\Shared\Menu\PreferencesMenuCurrentMarker;
use App\Shared\Twig\MenuTwigExtension;
use PHPUnit\Framework\TestCase;

final class MenuTwigExtensionTest extends TestCase
{
    public function testRegistersMenuCurrentFunctions(): void
    {
        $extension = new MenuTwigExtension(
            new PreferencesMenuCurrentMarker(),
            new AdministrationMenuCurrentMarker(),
        );

        $names = array_map(
            static fn ($function): string => $function->getName(),
            $extension->getFunctions(),
        );

        self::assertSame(
            ['beacon_preferences_menu_current', 'beacon_administration_menu_current'],
            $names,
        );
    }

    public function testDelegatesPreferencesMarking(): void
    {
        $display = new class {
            public function getRouteName(): string
            {
                return 'account_display';
            }
        };

        $tree = [
            ['item' => $display, 'children' => [], 'isCurrent' => false, 'hasCurrentInBranch' => false],
        ];

        $extension = new MenuTwigExtension(
            new PreferencesMenuCurrentMarker(),
            new AdministrationMenuCurrentMarker(),
        );

        $marked = $extension->markPreferencesCurrent($tree, 'account_display');

        self::assertTrue($marked[0]['isCurrent']);
        self::assertTrue($marked[0]['hasCurrentInBranch']);
    }

    public function testDelegatesAdministrationMarking(): void
    {
        $httpLog = new class {
            public function getRouteName(): ?string
            {
                return 'nowo_http_log_admin_index';
            }
        };

        $tree = [
            ['item' => $httpLog, 'children' => [], 'isCurrent' => false, 'hasCurrentInBranch' => false],
        ];

        $extension = new MenuTwigExtension(
            new PreferencesMenuCurrentMarker(),
            new AdministrationMenuCurrentMarker(),
        );

        $marked = $extension->markAdministrationCurrent($tree, 'nowo_http_log_admin_show');

        self::assertTrue($marked[0]['isCurrent']);
        self::assertTrue($marked[0]['hasCurrentInBranch']);
    }
}
