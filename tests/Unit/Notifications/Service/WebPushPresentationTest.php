<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Service\WebPushPresentation;
use PHPUnit\Framework\TestCase;

final class WebPushPresentationTest extends TestCase
{
    public function testEnrichAddsChromeFieldsForKitServiceWorker(): void
    {
        $payload = (new WebPushPresentation())->enrich(MemberAlertEvent::IssueAssigned, [
            'summary' => 'ignored when issue present',
            'project' => ['name' => 'Demo'],
            'issue' => [
                'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'title' => "App\\Foo\\BarException\nsecond line",
                'culprit' => 'ignored when title set',
            ],
            'url' => 'https://beacon.test/projects/p/issues/i',
        ]);

        self::assertSame('Issue assigned', $payload['title']);
        self::assertSame('Demo · BarException', $payload['body']);
        self::assertSame('issue-aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $payload['tag']);
        self::assertSame('/icons/icon-192.png', $payload['icon']);
        self::assertSame('https://beacon.test/projects/p/issues/i', $payload['url']);
        self::assertSame('issue.assigned', $payload['event']);
    }

    public function testEnrichFallsBackToSummaryAndDashboard(): void
    {
        $payload = (new WebPushPresentation())->enrich(MemberAlertEvent::IssueNew, [
            'summary' => 'New issue: [error] Boom',
        ]);

        self::assertSame('New issue', $payload['title']);
        self::assertSame('New issue: [error] Boom', $payload['body']);
        self::assertSame('beacon-issue', $payload['tag']);
        self::assertSame('/dashboard', $payload['url']);
    }
}
