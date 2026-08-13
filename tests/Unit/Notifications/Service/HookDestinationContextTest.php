<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Service\HookDestinationContext;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class HookDestinationContextTest extends TestCase
{
    public function testHoldsDestinationProjectAndSecret(): void
    {
        $destination = new NotificationDestination();
        $project = new Project();
        $ctx = new HookDestinationContext($destination, $project, 'signing-secret');

        self::assertSame($destination, $ctx->destination);
        self::assertSame($project, $ctx->project);
        self::assertSame('signing-secret', $ctx->signingSecret);
    }
}
