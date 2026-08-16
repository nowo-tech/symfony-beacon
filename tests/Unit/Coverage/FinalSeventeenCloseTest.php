<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coverage;

use App\Setup\SiteBackupSecurityDefaultsGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;

final class FinalSeventeenCloseTest extends TestCase
{
    public function testSiteBackupGuardSubscribedEvents(): void
    {
        $events = SiteBackupSecurityDefaultsGuard::getSubscribedEvents();
        self::assertSame(['onRequest', 1024], $events[KernelEvents::REQUEST]);
        self::assertSame(['onConsole', 1024], $events[ConsoleEvents::COMMAND]);
    }
}
