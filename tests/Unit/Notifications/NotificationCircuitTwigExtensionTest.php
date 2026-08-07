<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Twig\NotificationCircuitTwigExtension;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NotificationCircuitTwigExtensionTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testExposesCircuitOpenHelper(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setNotificationCircuitBreakerThreshold(3);
            $settings->setNotificationCircuitBreakerCooldownMinutes(60);
        });
        $breaker = new NotificationCircuitBreaker($ops);
        $extension = new NotificationCircuitTwigExtension($breaker);

        $destination = new NotificationDestination();
        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('beacon_notification_circuit_open', $functions[0]->getName());
        self::assertFalse($extension->isCircuitOpen($destination));

        // Use "now" so the cooldown window has not expired (isOpen() expires cooled circuits).
        $destination->openCircuit(new DateTimeImmutable());
        self::assertTrue($extension->isCircuitOpen($destination));
    }
}
