<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Realtime\IssueRealtimeTopics;
use PHPUnit\Framework\TestCase;

final class IssueRealtimeTopicsTest extends TestCase
{
    public function testForProjectBuildsStableTopic(): void
    {
        self::assertSame(
            '/projects/00000000-0000-4000-8000-000000000099/issues',
            IssueRealtimeTopics::forProject('00000000-0000-4000-8000-000000000099'),
        );
    }

    public function testForUserBuildsMemberAlertTopic(): void
    {
        self::assertSame(
            '/users/00000000-0000-4000-8000-000000000099/member-alerts',
            IssueRealtimeTopics::forUser('00000000-0000-4000-8000-000000000099'),
        );
    }
}
