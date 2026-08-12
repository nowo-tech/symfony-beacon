<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\AccountSecurityActivity;
use App\Identity\AdminAuditFilter;
use App\Identity\AdminIdentityAudit;
use App\Identity\DashboardProductActivity;
use App\Identity\UserActionType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class IdentityAllowlistsAndAuditFilterTest extends TestCase
{
    public function testSecurityActivityAllowlist(): void
    {
        $types = AccountSecurityActivity::actionTypes();

        self::assertContains(UserActionType::MagicLoginRequested, $types);
        self::assertContains(UserActionType::PasswordResetRequested, $types);
        self::assertSame(50, AccountSecurityActivity::TIMELINE_LIMIT);
    }

    public function testDashboardProductActivityAllowlist(): void
    {
        $types = DashboardProductActivity::types();

        self::assertContains(UserActionType::IssueOpened, $types);
        self::assertContains(UserActionType::AnalyticsOpened, $types);
        self::assertNotContains(UserActionType::UserCreated, $types);
    }

    public function testAdminIdentityAuditAllowlists(): void
    {
        self::assertContains(UserActionType::UserAnonymized, AdminIdentityAudit::userTimelineActions());
        self::assertContains(UserActionType::GroupCreated, AdminIdentityAudit::groupTimelineActions());
        self::assertNotContains(UserActionType::IssueOpened, AdminIdentityAudit::userTimelineActions());
        self::assertSame(100, AdminIdentityAudit::TIMELINE_LIMIT);
    }

    public function testAdminAuditFilterParsesValidQuery(): void
    {
        $request = Request::create('/', Request::METHOD_GET, [
            'action' => UserActionType::UserCreated->value,
            'from' => '2026-01-02',
            'to' => '2026-01-03',
        ]);

        $parsed = AdminAuditFilter::fromRequest($request, AdminIdentityAudit::userTimelineActions());

        self::assertSame(UserActionType::UserCreated, $parsed['action']);
        self::assertEquals(new DateTimeImmutable('2026-01-02 00:00:00'), $parsed['from']);
        self::assertEquals(new DateTimeImmutable('2026-01-03 23:59:59'), $parsed['to']);
        self::assertSame(UserActionType::UserCreated->value, $parsed['filter']['action']);
        self::assertSame('2026-01-02', $parsed['filter']['from']);
        self::assertSame('2026-01-03', $parsed['filter']['to']);
    }

    public function testAdminAuditFilterRejectsUnknownActionAndBadDates(): void
    {
        $request = Request::create('/', Request::METHOD_GET, [
            'action' => UserActionType::IssueOpened->value,
            'from' => 'not-a-date',
            'to' => '2026-13-40',
        ]);

        $parsed = AdminAuditFilter::fromRequest($request, AdminIdentityAudit::userTimelineActions());

        self::assertNull($parsed['action']);
        self::assertNull($parsed['from']);
        self::assertNull($parsed['to']);
        // Unknown actions resolve to null and clear the echoed filter action.
        self::assertSame('', $parsed['filter']['action']);
        self::assertSame('not-a-date', $parsed['filter']['from']);
        self::assertSame('2026-13-40', $parsed['filter']['to']);
    }
}
