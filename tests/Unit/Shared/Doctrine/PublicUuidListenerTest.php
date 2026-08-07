<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Doctrine;

use App\Shared\Doctrine\PublicUuidListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;

final class PublicUuidListenerTest extends TestCase
{
    public function testCallsEnsureUuidWhenPresent(): void
    {
        $entity = new class {
            public bool $called = false;

            public function ensureUuid(): void
            {
                $this->called = true;
            }
        };

        $args = new PrePersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
        (new PublicUuidListener())->prePersist($args);

        self::assertTrue($entity->called);
    }

    public function testSkipsEntitiesWithoutEnsureUuid(): void
    {
        $entity = new class {
            public string $name = 'plain';
        };

        $args = new PrePersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
        (new PublicUuidListener())->prePersist($args);

        $this->addToAssertionCount(1);
    }
}
