<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Entity;

use App\Identity\Entity\User;
use App\Notifications\Entity\NotificationDeliveryAttempt;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Project\Entity\Project;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class NotificationDestinationTest extends TestCase
{
    public function testNormalizesFieldsAndMasksEndpoints(): void
    {
        $destination = new NotificationDestination();
        $project = new Project();
        $user = new User();

        $destination
            ->setProject($project)
            ->setLabel('  Slack alerts  ')
            ->setType(NotificationDestinationType::Slack)
            ->setEndpointUrl('  https://hooks.slack.com/services/T00/B00/XXX  ')
            ->setSigningSecret('  sign-me  ')
            ->setEnabled(false)
            ->setCategories(['error', 'invalid', 'warning', 'error'])
            ->setQuietHoursEnabled(true)
            ->setQuietHoursTimezone('  ')
            ->setQuietHoursStart(' 09:00 ')
            ->setQuietHoursEnd(' 17:30 ')
            ->setDigestEnabled(true);
        $destination->setCreatedBy(new stdClass());
        $destination->setUpdatedBy($user);

        self::assertNotSame('', $destination->getUuid());
        self::assertSame($project, $destination->getProject());
        self::assertSame('Slack alerts', $destination->getLabel());
        self::assertSame(NotificationDestinationType::Slack, $destination->getType());
        self::assertSame('https://hooks.slack.com/services/T00/B00/XXX', $destination->getEndpointUrl());
        self::assertSame('sign-me', $destination->getSigningSecret());
        self::assertTrue($destination->hasSigningSecret());
        self::assertFalse($destination->isEnabled());
        self::assertSame(['error', 'warning'], $destination->getCategories());
        self::assertTrue($destination->matchesCategory('warning'));
        self::assertFalse($destination->matchesCategory('info'));
        self::assertTrue($destination->isQuietHoursEnabled());
        self::assertSame('UTC', $destination->getQuietHoursTimezone());
        self::assertSame('09:00', $destination->getQuietHoursStart());
        self::assertSame('17:30', $destination->getQuietHoursEnd());
        self::assertTrue($destination->isDigestEnabled());
        self::assertNull($destination->getCreatedBy());
        self::assertSame($user, $destination->getUpdatedBy());
        self::assertStringStartsWith('https://hook', $destination->maskedEndpointUrl());

        $destination->setType(NotificationDestinationType::Email);
        $destination->setEndpointUrl('ab@example.com');
        self::assertSame('ab••@example.com', $destination->maskedEndpointUrl());

        $destination->setEndpointUrl('z@example.com');
        self::assertSame('z••@example.com', $destination->maskedEndpointUrl());

        $destination->setType(NotificationDestinationType::Telegram);
        $destination->setEndpointUrl('123456:abcDEF@alerts-room');
        self::assertSame('••••…••••@alerts-room', $destination->maskedEndpointUrl());

        $destination->setEndpointUrl('123456');
        self::assertSame('••••@••••', $destination->maskedEndpointUrl());

        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('short');
        self::assertSame('•••••', $destination->maskedEndpointUrl());

        $destination->setSigningSecret('   ');
        self::assertFalse($destination->hasSigningSecret());

        $destination
            ->setQuietHoursStart('   ')
            ->setQuietHoursEnd(null);
        $destination->setCreatedBy($user);
        $destination->setUpdatedBy(new stdClass());
        self::assertNull($destination->getQuietHoursStart());
        self::assertNull($destination->getQuietHoursEnd());
        self::assertSame($user, $destination->getCreatedBy());
        self::assertNull($destination->getUpdatedBy());
    }

    public function testTracksDeliveryCircuitAndAttempts(): void
    {
        $destination = new NotificationDestination();
        $openedAt = new DateTimeImmutable('2026-08-16T09:00:00+00:00');
        $deliveredAt = new DateTimeImmutable('2026-08-16T10:00:00+00:00');
        $secondAt = new DateTimeImmutable('2026-08-16T11:00:00+00:00');

        $destination->incrementConsecutiveFailures()->incrementConsecutiveFailures();
        self::assertSame(2, $destination->getConsecutiveFailures());

        $destination->openCircuit($openedAt);
        self::assertSame($openedAt, $destination->getCircuitOpenedAt());

        $destination->clearCircuitOpenedAt();
        self::assertNull($destination->getCircuitOpenedAt());

        $destination->recordDeliveryFailure(str_repeat('x', 3005), $deliveredAt);
        self::assertSame($deliveredAt, $destination->getLastDeliveryAt());
        self::assertFalse($destination->isLastDeliverySuccess());
        self::assertSame(2000, \strlen((string) $destination->getLastDeliveryError()));

        $destination->openCircuit($openedAt);
        $destination->recordDeliverySuccess($secondAt);
        self::assertSame($secondAt, $destination->getLastDeliveryAt());
        self::assertTrue($destination->isLastDeliverySuccess());
        self::assertNull($destination->getLastDeliveryError());
        self::assertSame(0, $destination->getConsecutiveFailures());
        self::assertNull($destination->getCircuitOpenedAt());

        $destination->incrementConsecutiveFailures()->openCircuit();
        $destination->resumeCircuit();
        self::assertSame(0, $destination->getConsecutiveFailures());
        self::assertNull($destination->getCircuitOpenedAt());

        $older = new NotificationDeliveryAttempt()
            ->setAttemptedAt(new DateTimeImmutable('2026-08-16T08:00:00+00:00'))
            ->setErrorSnippet(' older ')
            ->setSuccessful(false);
        $newer = new NotificationDeliveryAttempt()
            ->setAttemptedAt(new DateTimeImmutable('2026-08-16T08:00:00+00:00'))
            ->setErrorSnippet('should clear')
            ->setSuccessful(true);
        $latest = new NotificationDeliveryAttempt()
            ->setAttemptedAt(new DateTimeImmutable('2026-08-16T12:00:00+00:00'))
            ->setSuccessful(false);

        new ReflectionProperty(NotificationDeliveryAttempt::class, 'id')->setValue($older, 1);
        new ReflectionProperty(NotificationDeliveryAttempt::class, 'id')->setValue($newer, 2);
        new ReflectionProperty(NotificationDeliveryAttempt::class, 'id')->setValue($latest, 3);

        $destination->addDeliveryAttempt($older)->addDeliveryAttempt($newer)->addDeliveryAttempt($latest);
        $destination->addDeliveryAttempt($older);

        self::assertCount(3, $destination->getDeliveryAttempts());
        self::assertSame($destination, $older->getDestination());
        self::assertNull($newer->getErrorSnippet());
        self::assertSame('older', $older->getErrorSnippet());

        $excess = $destination->trimDeliveryAttempts(0);

        self::assertCount(2, $excess);
        self::assertSame([$newer, $older], $excess);
        self::assertCount(1, $destination->getDeliveryAttempts());
        self::assertSame($latest, $destination->getDeliveryAttempts()->first());
        self::assertNull($older->getDestination());
        self::assertNull($newer->getDestination());
    }
}
