<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Menu\PreferencesMenuCurrentMarker;
use PHPUnit\Framework\TestCase;

final class PreferencesMenuCurrentMarkerTest extends TestCase
{
    public function testMarksDisplayCurrentForTourSubRoute(): void
    {
        $marker = new PreferencesMenuCurrentMarker();
        $display = new class {
            public function getRouteName(): string
            {
                return 'account_display';
            }
        };
        $security = new class {
            public function getRouteName(): string
            {
                return 'account_security';
            }
        };

        $tree = $marker->mark([
            ['item' => $security, 'children' => [], 'isCurrent' => false, 'hasCurrentInBranch' => false],
            ['item' => $display, 'children' => [], 'isCurrent' => false, 'hasCurrentInBranch' => false],
        ], 'account_display_tours');

        self::assertFalse($tree[0]['isCurrent']);
        self::assertTrue($tree[1]['isCurrent']);
        self::assertTrue($tree[1]['hasCurrentInBranch']);
    }
}
