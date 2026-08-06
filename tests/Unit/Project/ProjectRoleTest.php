<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Project\Enum\ProjectRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectRoleTest extends TestCase
{
    #[DataProvider('capabilityProvider')]
    public function testCapabilities(
        ProjectRole $role,
        bool $members,
        bool $apiKeys,
        bool $delete,
        bool $triage,
        int $rank,
    ): void {
        self::assertSame($members, $role->canManageMembers());
        self::assertSame($apiKeys, $role->canManageApiKeys());
        self::assertSame($delete, $role->canDeleteProject());
        self::assertSame($triage, $role->canTriageIssues());
        self::assertSame($rank, $role->rank());
    }

    /**
     * @return iterable<string, array{ProjectRole, bool, bool, bool, bool, int}>
     */
    public static function capabilityProvider(): iterable
    {
        yield 'owner' => [ProjectRole::Owner, true, true, true, true, 3];
        yield 'admin' => [ProjectRole::Admin, true, true, false, true, 2];
        yield 'member' => [ProjectRole::Member, false, false, false, true, 1];
        yield 'viewer' => [ProjectRole::Viewer, false, false, false, false, 0];
    }
}
