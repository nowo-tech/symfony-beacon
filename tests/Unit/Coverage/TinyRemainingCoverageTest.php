<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coverage;

use App\Issues\Entity\Event;
use App\Performance\Repository\PerfSpanRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class TinyRemainingCoverageTest extends TestCase
{
    public function testEventRequestUrlGetterReturnsStoredValue(): void
    {
        $event = new Event();
        $event->setRequestUrl('https://beacon.test/issues/1');

        self::assertSame('https://beacon.test/issues/1', $event->getRequestUrl());
    }

    public function testPerfSpanRepositoryCanBeConstructed(): void
    {
        new PerfSpanRepository($this->createStub(ManagerRegistry::class));
        $this->addToAssertionCount(1);
    }
}
