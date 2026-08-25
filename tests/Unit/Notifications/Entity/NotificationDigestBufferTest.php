<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Entity;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\NotificationDigestBuffer;
use App\Notifications\Enum\NotificationDestinationType;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class NotificationDigestBufferTest extends TestCase
{
    public function testAccessorsExposeDestinationPayloadAndTimestamp(): void
    {
        $project = new Project()->setName('Beacon')->setSlug('beacon');
        $destination = new NotificationDestination()
            ->setProject($project)
            ->setLabel('Ops')
            ->setType(NotificationDestinationType::Http)
            ->setEndpointUrl('https://example.test/hook')
            ->setEnabled(true);

        $buffer = new NotificationDigestBuffer();
        $buffer
            ->setDestination($destination)
            ->setPayload(['event' => 'issue.new', 'count' => 2]);

        self::assertNull($buffer->getId());
        self::assertSame($destination, $buffer->getDestination());
        self::assertSame(['event' => 'issue.new', 'count' => 2], $buffer->getPayload());
        self::assertGreaterThan(0, $buffer->getCreatedAt()->getTimestamp());
    }
}
