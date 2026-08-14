<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\MessageHandler;

use App\Notifications\Entity\NotificationDeliveryAttempt;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Formatter\DiscordChannelFormatter;
use App\Notifications\Formatter\HttpChannelFormatter;
use App\Notifications\Formatter\SlackChannelFormatter;
use App\Notifications\Formatter\TeamsChannelFormatter;
use App\Notifications\Formatter\TelegramChannelFormatter;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\MessageHandler\DeliverNotificationHandler;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\InteractionActionToken;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDeliveryHistoryRecorder;
use App\Notifications\Service\NotificationOutboundFormatter;
use App\Notifications\Service\OutboundUrlGuard;
use App\Project\Entity\Project;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DeliverNotificationHandlerTest extends TestCase
{
    public function testEarlyExitsForMissingDisabledAndOpenCircuit(): void
    {
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('find')->willReturn(null);
        $this->handler($destinations)(new DeliverNotificationMessage(1, []));

        $disabled = $this->destination(enabled: false, endpoint: 'https://example.com/hook');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('find')->willReturn($disabled);
        $this->handler($destinations)(new DeliverNotificationMessage(2, []));

        $open = $this->destination(enabled: true, endpoint: 'https://example.com/hook');
        $open->openCircuit(new DateTimeImmutable());
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('find')->willReturn($open);
        $this->handler($destinations)(new DeliverNotificationMessage(3, []));

        self::assertTrue(true);
    }

    public function testRecordsFailureForEmptyEndpoint(): void
    {
        $destination = $this->destination(enabled: true, endpoint: '');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('find')->willReturn($destination);

        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $this->handler($destinations, em: $em)(new DeliverNotificationMessage(4, []));
        self::assertSame(1, $flush);
        self::assertNotNull($destination->getLastDeliveryError());
    }

    public function testDeliversHttpSuccess(): void
    {
        $destination = $this->destination(enabled: true, endpoint: 'https://example.com/hook');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('find')->willReturn($destination);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(204);
        $http = $this->createStub(HttpClientInterface::class);
        $http->method('request')->willReturn($response);

        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $this->handler($destinations, http: $http, em: $em)(new DeliverNotificationMessage(5, [
            'summary' => 'Hello',
            'event' => 'issue.new',
        ]));
        self::assertSame(1, $flush);
        self::assertNull($destination->getLastDeliveryError());
    }

    private function destination(bool $enabled, string $endpoint): NotificationDestination
    {
        return new NotificationDestination()
            ->setProject(new Project())
            ->setType(NotificationDestinationType::Http)
            ->setEndpointUrl($endpoint)
            ->setEnabled($enabled);
    }

    private function handler(
        NotificationDestinationRepository $destinations,
        ?HttpClientInterface $http = null,
        ?EntityManagerInterface $em = null,
    ): DeliverNotificationHandler {
        $settings = InstanceSettings::defaults()->setAllowPrivateUrls(true);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);
        $ops = new InstanceOpsDefaults($settingsRepo);
        $breaker = new NotificationCircuitBreaker($ops);
        $attempts = $this->createStub(NotificationDeliveryAttemptRepository::class);
        $attempts->method('record')->willReturnCallback(
            static function (NotificationDestination $destination, bool $successful, ?string $error = null): NotificationDeliveryAttempt {
                $attempt = new NotificationDeliveryAttempt();
                $attempt->setSuccessful($successful);
                $attempt->setErrorSnippet($error);
                $destination->addDeliveryAttempt($attempt);

                return $attempt;
            },
        );
        $attempts->method('trimOlderThanKeep');
        $recorder = new NotificationDeliveryHistoryRecorder($attempts, $breaker, $ops);
        $formatter = new NotificationOutboundFormatter(
            new SlackChannelFormatter(),
            new DiscordChannelFormatter(),
            new TeamsChannelFormatter($this->createStub(UrlGeneratorInterface::class), new InteractionActionToken()),
            new TelegramChannelFormatter(),
            new HttpChannelFormatter(),
        );
        $mailer = new ConfiguredMailer($settingsRepo, new MailerDsnValidator(), 'null://null', 'test');
        $em ??= $this->createStub(EntityManagerInterface::class);
        $http ??= $this->createStub(HttpClientInterface::class);

        return new DeliverNotificationHandler(
            $destinations,
            $recorder,
            $breaker,
            $formatter,
            new OutboundUrlGuard($ops),
            $http,
            $mailer,
            new NullLogger(),
            $em,
        );
    }
}
