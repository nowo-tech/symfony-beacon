<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Entity;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use PHPUnit\Framework\TestCase;

final class NotificationDestinationExtraTest extends TestCase
{
    public function testMaskedEndpointUrlUsesPlaceholderForMalformedEmailAddress(): void
    {
        $destination = new NotificationDestination()
            ->setType(NotificationDestinationType::Email)
            ->setEndpointUrl('invalid-email-value');

        self::assertSame('••••', $destination->maskedEndpointUrl());
    }
}
