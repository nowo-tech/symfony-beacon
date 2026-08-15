<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Dto;

use App\Issues\Dto\IssueJsonView;
use PHPUnit\Framework\TestCase;

final class IssueJsonViewTest extends TestCase
{
    public function testConstructsReadonlyView(): void
    {
        $view = new IssueJsonView(
            uuid: 'u-1',
            title: 'Crash',
            level: 'error',
            status: 'unresolved',
            priority: 'high',
            culprit: 'app.php',
            eventCount: 3,
            firstSeen: '2026-01-01T00:00:00+00:00',
            lastSeen: '2026-01-02T00:00:00+00:00',
            firstRelease: '1.0.0',
            lastRelease: '1.1.0',
            lastEnvironment: 'prod',
            assigneeEmail: 'dev@example.com',
            duplicateOfUuid: null,
        );

        self::assertSame('u-1', $view->uuid);
        self::assertSame('Crash', $view->title);
        self::assertSame(3, $view->eventCount);
        self::assertNull($view->duplicateOfUuid);
    }
}
