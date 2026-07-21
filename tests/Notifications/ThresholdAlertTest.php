<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use App\Notifications\Service\VolumeThresholdEvaluator;
use App\Shared\IssueStatus;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ThresholdAlertTest extends DatabaseWebTestCase
{
    public function testThresholdDispatchesOnceAndCooldownSuppressesImmediateRepeat(): void
    {
        [, , $project] = $this->bootWithDemoProject('threshold@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable('2026-07-21 12:00:00');

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('HTTP');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled(true);
        $destination->setCategories([NotificationCategories::VOLUME_THRESHOLD]);
        $project->addNotificationDestination($destination);
        $em->persist($destination);

        $rule = new ProjectThresholdRule();
        $rule->setProject($project);
        $rule->setLabel('Production spike');
        $rule->setEnabled(true);
        $rule->setErrorCount(3);
        $rule->setWindowMinutes(15);
        $rule->setCooldownMinutes(60);
        $rule->setEnvironment('production');
        $rule->setReleaseVersion('1.2.3');
        $project->addThresholdRule($rule);
        $em->persist($rule);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('threshold-spike');
        $issue->setTitle('Threshold spike');
        $issue->setCulprit('App\\Runner::run');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setEventCount(3);
        $issue->setFirstSeen($now->modify('-10 minutes'));
        $issue->setLastSeen($now);
        $em->persist($issue);

        foreach ([12, 8, 2] as $minutesAgo) {
            $event = new Event();
            $event->setIssue($issue);
            $event->setEventId('evt-threshold-'.$minutesAgo);
            $event->setPayload(['level' => 'error']);
            $event->setEnvironment('production');
            $event->setReleaseVersion('1.2.3');
            $event->setReceivedAt($now->modify('-'.$minutesAgo.' minutes'));
            $event->setEventTimestamp($now->modify('-'.$minutesAgo.' minutes'));
            $em->persist($event);
        }

        $em->flush();

        $requests = [];
        $mock = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $body = isset($options['body']) && \is_string($options['body'])
                ? json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR)
                : [];
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'json' => \is_array($body) ? $body : [],
            ];

            return new MockResponse('ok', ['http_code' => 200]);
        });
        self::getContainer()->set(HttpClientInterface::class, $mock);

        $evaluator = self::getContainer()->get(VolumeThresholdEvaluator::class);
        $evaluator->evaluate($project, 'production', '1.2.3', $now);

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://example.test/hook', $requests[0]['url']);
        self::assertSame(NotificationCategories::VOLUME_THRESHOLD, $requests[0]['json']['category'] ?? null);
        self::assertSame(3, $requests[0]['json']['count'] ?? null);
        self::assertSame(3, $requests[0]['json']['threshold'] ?? null);
        self::assertSame(15, $requests[0]['json']['window_minutes'] ?? null);
        self::assertSame('production', $requests[0]['json']['environment'] ?? null);
        self::assertSame('1.2.3', $requests[0]['json']['release'] ?? null);

        $em->refresh($rule);
        $em->refresh($destination);
        self::assertNotNull($rule->getLastFiredAt());
        self::assertNotNull($destination->getLastDeliveryAt());
        self::assertTrue($destination->isLastDeliverySuccess());

        $evaluator->evaluate($project, 'production', '1.2.3', $now->modify('+1 minute'));
        self::assertCount(1, $requests);
    }
}
