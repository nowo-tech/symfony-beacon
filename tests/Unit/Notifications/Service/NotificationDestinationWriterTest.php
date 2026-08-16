<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Service\NotificationDestinationWriter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NotificationDestinationWriterTest extends TestCase
{
    public function testResumeCircuitAndDeleteFlushEntityManager(): void
    {
        $destination = new NotificationDestination();
        $removed = [];
        $flushes = 0;

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $em->method('flush')->willReturnCallback(static function () use (&$flushes): void {
            ++$flushes;
        });

        $writer = new NotificationDestinationWriter($em);
        $writer->resumeCircuit($destination);
        $writer->delete($destination);

        self::assertSame([$destination], $removed);
        self::assertSame(2, $flushes);
    }
}
