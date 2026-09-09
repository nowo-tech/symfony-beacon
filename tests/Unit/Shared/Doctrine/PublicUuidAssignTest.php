<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Doctrine;

use App\Project\Entity\Project;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PublicUuidAssignTest extends TestCase
{
    public function testAssignUuidAcceptsValidRfc4122(): void
    {
        $project = new Project();
        $uuid = '0192f3c4-5d6e-7a8b-9c0d-1e2f3a4b5c6d';
        $project->assignUuid($uuid);
        self::assertSame($uuid, $project->getUuid());
    }

    public function testAssignUuidNoOpOnEmpty(): void
    {
        $project = new Project();
        $before = $project->getUuid();
        $project->assignUuid('   ');
        self::assertSame($before, $project->getUuid());
    }

    public function testAssignUuidRejectsGarbage(): void
    {
        $project = new Project();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_uuid:not-a-uuid');
        $project->assignUuid('not-a-uuid');
    }
}
