<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Command;

use App\Notifications\Command\FlushNotificationDigestsCommand;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationDigestFlusher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FlushNotificationDigestsCommandTest extends TestCase
{
    public function testExecuteReportsFlushTotals(): void
    {
        $buffer = $this->createStub(NotificationDigestBufferRepository::class);
        $buffer->method('findDestinationsWithBufferedItems')->willReturn([]);
        $flusher = new NotificationDigestFlusher(
            $buffer,
            new QuietHoursEvaluator(),
            new NotificationPayloadBuilder($this->createStub(UrlGeneratorInterface::class)),
            $this->createStub(MessageBusInterface::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $tester = new CommandTester(new FlushNotificationDigestsCommand($flusher));
        self::assertSame(0, $tester->execute(['--force' => true]));
        self::assertStringContainsString('Flushed 0 destination(s)', $tester->getDisplay());
        self::assertStringContainsString('queued 0 message(s)', $tester->getDisplay());
    }
}
