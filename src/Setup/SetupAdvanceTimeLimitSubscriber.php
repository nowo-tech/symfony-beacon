<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * FrankenPHP worker mode does not always reset PHP's request timer between requests.
 * SiteBackup {@code /setup/api/advance} runs migrate/seed subprocesses up to
 * {@code process_timeout} seconds — bump {@see set_time_limit()} so the parent
 * request is not killed mid-pipe (MaxExecutionTimeError on AbstractPipes).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
final readonly class SetupAdvanceTimeLimitSubscriber
{
    public function __construct(
        #[Autowire('%nowo.site_backup.process_timeout%')]
        private int $processTimeoutSeconds,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!preg_match('#^(?:/[a-z]{2})?/setup(?:/|$)#', $path)) {
            return;
        }

        $seconds = max(0, $this->processTimeoutSeconds);
        set_time_limit($seconds);
    }
}
