<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\AccountSecurityActivity;
use App\Identity\AdminAuditFilter;
use App\Identity\AdminIdentityAudit;
use App\Identity\DashboardProductActivity;
use App\Identity\Tour\ProductTourPage;
use App\Identity\UserActionType;
use App\Issues\Form\IssueListFilterFields;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class IdentityAllowlistsAndFiltersTest extends TestCase
{
    public function testAllowlistsAreNonEmpty(): void
    {
        self::assertContains(UserActionType::MagicLoginRequested, AccountSecurityActivity::actionTypes());
        self::assertSame(50, AccountSecurityActivity::TIMELINE_LIMIT);
        self::assertContains(UserActionType::UserCreated, AdminIdentityAudit::userTimelineActions());
        self::assertContains(UserActionType::GroupCreated, AdminIdentityAudit::groupTimelineActions());
        self::assertContains(UserActionType::IssueOpened, DashboardProductActivity::types());
        self::assertSame(ProductTourPage::cases(), ProductTourPage::all());
    }

    public function testAdminAuditFilterParsesQuery(): void
    {
        $allowed = AdminIdentityAudit::userTimelineActions();
        $request = Request::create('/', 'GET', [
            'action' => UserActionType::UserCreated->value,
            'from' => '2026-08-01',
            'to' => '2026-08-13',
        ]);
        $parsed = AdminAuditFilter::fromRequest($request, $allowed);
        self::assertSame(UserActionType::UserCreated, $parsed['action']);
        self::assertInstanceOf(DateTimeImmutable::class, $parsed['from']);
        self::assertSame('00:00:00', $parsed['from']->format('H:i:s'));
        self::assertSame('23:59:59', $parsed['to']->format('H:i:s'));
        self::assertSame(UserActionType::UserCreated->value, $parsed['filter']['action']);

        $bad = AdminAuditFilter::fromRequest(Request::create('/', 'GET', [
            'action' => 'not-a-real-action',
            'from' => 'bad-date',
        ]), $allowed);
        self::assertNull($bad['action']);
        self::assertNull($bad['from']);
    }

    public function testIssueListFilterFieldsChoices(): void
    {
        $priority = IssueListFilterFields::priorityChoicesWithAny('issues.filter');
        self::assertSame('', $priority['issues.filter.priority.any']);
        self::assertSame('high', $priority['issues.filter.priority.high']);
        self::assertArrayHasKey('unresolved', IssueListFilterFields::statusIdentityChoices());
        self::assertSame(IssueListFilterFields::LEVELS, array_values(IssueListFilterFields::levelIdentityChoices()));
        self::assertSame('', IssueListFilterFields::statusChoicesWithAny('x')['x.status.any']);
    }
}
