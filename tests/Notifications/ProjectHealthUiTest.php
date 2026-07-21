<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\NotificationDeliveryHistoryRecorder;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

final class ProjectHealthUiTest extends DatabaseWebTestCase
{
    public function testSettingsShowsHealthPanelAndDeliveryStatus(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject();
        $em = self::getContainer()->get('doctrine')->getManager();
        $recorder = self::getContainer()->get(NotificationDeliveryHistoryRecorder::class);

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
        $recorder->recordFailure($destination, 'Destination returned HTTP 500', new DateTimeImmutable('2026-07-21 12:00:00'));
        $recorder->recordSuccess($destination, new DateTimeImmutable('2026-07-21 12:05:00'));
        $em->flush();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Health');
        self::assertSelectorTextContains('body', 'Ops Hook');
        self::assertSelectorTextContains('body', 'Show recent attempts (2)');
        self::assertSelectorTextContains('body', 'Success');
        self::assertSelectorTextContains('body', 'Failed');
        self::assertSelectorTextContains('body', 'HTTP 500');
        self::assertSelectorExists('a[href="/health/ready"]');
    }

    public function testRecordDeliverySuccessClearsError(): void
    {
        $destination = new NotificationDestination();
        $destination->recordDeliveryFailure('boom');
        self::assertFalse($destination->isLastDeliverySuccess());
        self::assertSame('boom', $destination->getLastDeliveryError());
        self::assertInstanceOf(DateTimeImmutable::class, $destination->getLastDeliveryAt());

        $destination->recordDeliverySuccess();
        self::assertTrue($destination->isLastDeliverySuccess());
        self::assertNull($destination->getLastDeliveryError());
    }
}
