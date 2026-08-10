<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Entity;

use App\Project\Entity\ProjectGroupAccess;
use App\Project\Enum\ProjectRole;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProjectGroupAccessTest extends TestCase
{
    public function testRejectsOwnerRole(): void
    {
        $access = new ProjectGroupAccess();

        $this->expectException(InvalidArgumentException::class);
        $access->setRole(ProjectRole::Owner);
    }

    public function testRejectsFullRole(): void
    {
        $access = new ProjectGroupAccess();

        $this->expectException(InvalidArgumentException::class);
        $access->setRole(ProjectRole::Full);
    }

    public function testAllowsAdminRole(): void
    {
        $access = new ProjectGroupAccess();
        $access->setRole(ProjectRole::Admin);

        self::assertSame(ProjectRole::Admin, $access->getRole());
    }
}
