<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDeliveryHistoryRecorder;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

final class NotificationCircuitBreakerTest extends DatabaseWebTestCase
{
    public function testTripsAfterThresholdFailuresAndResumeClears(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('circuit-owner@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $recorder = self::getContainer()->get(NotificationDeliveryHistoryRecorder::class);
        $breaker = self::getContainer()->get(NotificationCircuitBreaker::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Flaky Hook');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $threshold = $breaker->getThreshold();
        $base = new DateTimeImmutable('2026-07-31 12:00:00');
        for ($i = 0; $i < $threshold; ++$i) {
            $recorder->recordFailure($destination, \sprintf('fail-%d', $i), $base->modify(\sprintf('+%d minutes', $i)));
        }
        $em->flush();

        self::assertSame($threshold, $destination->getConsecutiveFailures());
        self::assertTrue($breaker->isOpen($destination));
        self::assertTrue($breaker->shouldSkipDelivery($destination));
        self::assertFalse($breaker->shouldSkipDelivery($destination, isSample: true));

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Auto-paused');

        $resumeToken = $crawler->filter('form[action*="/resume"] input[name="_token"]')->attr('value');
        self::assertNotEmpty($resumeToken);
        $client->request(
            Request::METHOD_POST,
            '/projects/'.$project->getUuid().'/notifications/'.$destination->getUuid().'/resume',
            ['_token' => $resumeToken],
        );
        self::assertResponseRedirects();

        $em->clear();
        $reloaded = $em->find(NotificationDestination::class, $destination->getId());
        self::assertNotNull($reloaded);
        self::assertSame(0, $reloaded->getConsecutiveFailures());
        self::assertNull($reloaded->getCircuitOpenedAt());
        self::assertFalse($breaker->isOpen($reloaded));
    }

    public function testSuccessResetsFailureCounter(): void
    {
        [, , $project] = $this->bootWithDemoProject('circuit-success@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $recorder = self::getContainer()->get(NotificationDeliveryHistoryRecorder::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ok Hook');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/ok');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $recorder->recordFailure($destination, 'once');
        $recorder->recordFailure($destination, 'twice');
        self::assertSame(2, $destination->getConsecutiveFailures());

        $recorder->recordSuccess($destination);
        self::assertSame(0, $destination->getConsecutiveFailures());
        self::assertNull($destination->getCircuitOpenedAt());
    }

    public function testOpenCircuitSkipsNonSampleDelivery(): void
    {
        [, , $project] = $this->bootWithDemoProject('circuit-dispatch@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $breaker = self::getContainer()->get(NotificationCircuitBreaker::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Paused Hook');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/paused');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $destination->openCircuit();
        $em->flush();
        self::assertTrue($breaker->shouldSkipDelivery($destination));
        self::assertFalse($breaker->shouldSkipDelivery($destination, isSample: true));
    }
}
