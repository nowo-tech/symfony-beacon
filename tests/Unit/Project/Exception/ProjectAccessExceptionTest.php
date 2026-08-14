<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Exception;

use App\Project\Exception\ProjectAccessException;
use PHPUnit\Framework\TestCase;

final class ProjectAccessExceptionTest extends TestCase
{
    public function testOfUsesReasonAsDefaultMessageAndDetectsForbidden(): void
    {
        $forbidden = ProjectAccessException::of(ProjectAccessException::FORBIDDEN);
        self::assertSame(ProjectAccessException::FORBIDDEN, $forbidden->reasonCode);
        self::assertSame(ProjectAccessException::FORBIDDEN, $forbidden->getMessage());
        self::assertTrue($forbidden->isForbidden());

        $custom = new ProjectAccessException(ProjectAccessException::LAST_OWNER, 'Cannot remove last owner');
        self::assertSame(ProjectAccessException::LAST_OWNER, $custom->reasonCode);
        self::assertSame('Cannot remove last owner', $custom->getMessage());
        self::assertFalse($custom->isForbidden());
    }
}
