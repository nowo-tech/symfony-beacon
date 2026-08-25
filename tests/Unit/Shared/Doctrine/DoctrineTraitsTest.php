<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Doctrine;

use App\Shared\Doctrine\CreatedAtImmutableTrait;
use App\Shared\Doctrine\PublicUuidTrait;
use PHPUnit\Framework\TestCase;

final class DoctrineTraitsTest extends TestCase
{
    public function testPublicUuidTraitAssignsOnce(): void
    {
        $entity = new class {
            use PublicUuidTrait;

            public function __construct()
            {
                $this->ensureUuid();
            }

            public function reensure(): void
            {
                $this->ensureUuid();
            }
        };

        $uuid = $entity->getUuid();
        self::assertNotSame('', $uuid);
        $entity->reensure();
        self::assertSame($uuid, $entity->getUuid());
    }

    public function testCreatedAtImmutableTrait(): void
    {
        $entity = new class {
            use CreatedAtImmutableTrait;

            public function __construct()
            {
                $this->initializeCreatedAt();
            }
        };

        self::assertGreaterThan(0, $entity->getCreatedAt()->getTimestamp());
    }
}
