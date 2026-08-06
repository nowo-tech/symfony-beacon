<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\NotificationCategories;
use PHPUnit\Framework\TestCase;

final class NotificationCategoriesTest extends TestCase
{
    public function testSanitizeDropsUnknownAndDedupes(): void
    {
        self::assertSame(
            ['error', NotificationCategories::N_PLUS_ONE],
            NotificationCategories::sanitize([
                'error',
                'not-a-category',
                NotificationCategories::N_PLUS_ONE,
                'error',
                1,
            ]),
        );
    }

    public function testIssueLevelAndLifecycleHelpers(): void
    {
        self::assertTrue(NotificationCategories::isIssueLevel('fatal'));
        self::assertFalse(NotificationCategories::isIssueLevel(NotificationCategories::N_PLUS_ONE));
        self::assertTrue(NotificationCategories::isLifecycle(NotificationCategories::ISSUE_RESOLVED));
        self::assertFalse(NotificationCategories::isLifecycle('error'));
    }

    public function testAllContainsLevelsLifecycleAndExtras(): void
    {
        foreach (NotificationCategories::ISSUE_LEVELS as $level) {
            self::assertContains($level, NotificationCategories::ALL);
        }
        foreach (NotificationCategories::LIFECYCLE as $lifecycle) {
            self::assertContains($lifecycle, NotificationCategories::ALL);
        }
        self::assertContains(NotificationCategories::VOLUME_THRESHOLD, NotificationCategories::ALL);
    }
}
