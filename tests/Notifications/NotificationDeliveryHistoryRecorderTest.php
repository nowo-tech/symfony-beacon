<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Notifications\Service\NotificationDeliveryHistoryRecorder;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;

final class NotificationDeliveryHistoryRecorderTest extends DatabaseWebTestCase
{
    public function testRecorderAppendsAttemptsPrunesOldRowsAndKeepsSummaryInSync(): void
    {
        [, , $project] = $this->bootWithDemoProject();
        $em = self::getContainer()->get('doctrine')->getManager();
        $recorder = self::getContainer()->get(NotificationDeliveryHistoryRecorder::class);
        $attempts = self::getContainer()->get(NotificationDeliveryAttemptRepository::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Hook');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $base = new DateTimeImmutable('2026-07-21 10:00:00');
        for ($i = 0; $i < 24; ++$i) {
            $timestamp = $base->modify(\sprintf('+%d minutes', $i));
            if (0 === $i % 4) {
                $recorder->recordFailure($destination, \sprintf('failure-%02d', $i), $timestamp);
                continue;
            }

            $recorder->recordSuccess($destination, $timestamp);
        }

        $latestError = str_repeat('x', 2500);
        $recorder->recordFailure($destination, $latestError, $base->modify('+24 minutes'));
        $em->flush();

        $recent = $attempts->findRecentForDestination($destination);

        self::assertCount(20, $recent);
        self::assertSame('2026-07-21 10:24', $recent[0]->getAttemptedAt()->format('Y-m-d H:i'));
        self::assertFalse($recent[0]->isSuccessful());
        self::assertSame(2000, mb_strlen((string) $recent[0]->getErrorSnippet()));
        self::assertSame('2026-07-21 10:05', $recent[19]->getAttemptedAt()->format('Y-m-d H:i'));

        self::assertSame('2026-07-21 10:24', $destination->getLastDeliveryAt()?->format('Y-m-d H:i'));
        self::assertFalse($destination->isLastDeliverySuccess());
        self::assertSame(str_repeat('x', 2000), $destination->getLastDeliveryError());
    }
}
