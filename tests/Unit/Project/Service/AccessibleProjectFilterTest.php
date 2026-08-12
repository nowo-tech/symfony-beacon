<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Project\Entity\Project;
use App\Project\Service\AccessibleProjectFilter;
use PHPUnit\Framework\TestCase;

final class AccessibleProjectFilterTest extends TestCase
{
    public function testEmptyUuidReturnsNull(): void
    {
        $project = new Project();
        $project->setSlug('a');
        $project->setName('A');

        self::assertNull(AccessibleProjectFilter::resolve([$project], ''));
    }

    public function testReturnsMatchingProject(): void
    {
        $a = new Project();
        $a->setSlug('a');
        $a->setName('A');
        $b = new Project();
        $b->setSlug('b');
        $b->setName('B');

        self::assertSame($b, AccessibleProjectFilter::resolve([$a, $b], $b->getUuid()));
    }

    public function testUnknownUuidReturnsNull(): void
    {
        $project = new Project();
        $project->setSlug('a');
        $project->setName('A');

        self::assertNull(AccessibleProjectFilter::resolve([$project], '00000000-0000-4000-8000-000000000000'));
        self::assertNull(AccessibleProjectFilter::resolve([], $project->getUuid()));
    }
}
