<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Controller\InboundEmailController;
use App\Issues\Repository\InboundEmailMessageRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\InboundEmailCommentHandler;
use App\Issues\Service\InboundEmailQuoteStripper;
use App\Issues\Service\InboundEmailReplyToken;
use App\Issues\Service\IssueCommentCreator;
use App\Issues\Service\IssueMentionParser;
use App\Issues\Service\IssueUserMailNotifier;
use App\Issues\Service\IssueUserMailTransport;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class InboundEmailControllerTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testDisabledReturnsNotFound(): void
    {
        $controller = $this->controller(enabled: false, secret: 'secret');
        $response = $controller(Request::create('/hooks/email/inbound', 'POST'));
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testMissingOrBadSecretReturnsUnauthorized(): void
    {
        $controller = $this->controller(enabled: true, secret: 'secret');

        $missing = $controller(Request::create('/hooks/email/inbound', 'POST', [
            'beacon_secret' => 'secret',
            'sender' => 'a@example.com',
            'recipient' => 'b@example.com',
            'body-plain' => 'hi',
        ]));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $missing->getStatusCode());

        $bad = $controller(Request::create(
            '/hooks/email/inbound',
            'POST',
            parameters: [
                'sender' => 'a@example.com',
                'recipient' => 'b@example.com',
                'body-plain' => 'hi',
            ],
            server: ['HTTP_X_BEACON_INBOUND_SECRET' => 'wrong'],
        ));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $bad->getStatusCode());
    }

    public function testIncompletePayloadReturnsBadRequest(): void
    {
        $controller = $this->controller(enabled: true, secret: 'secret');
        $response = $controller(Request::create(
            '/hooks/email/inbound',
            'POST',
            parameters: ['recipient' => 'inbox@example.com', 'body-plain' => 'hi'],
            server: ['HTTP_X_BEACON_INBOUND_SECRET' => 'secret'],
        ));
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testValidWebhookReturnsHandlerResult(): void
    {
        $controller = $this->controller(enabled: true, secret: 'secret');

        $response = $controller(Request::create(
            '/hooks/email/inbound',
            'POST',
            parameters: [
                'from' => 'Alice <alice@example.com>',
                'recipient' => 'inbox@example.com',
                'body-plain' => 'Hello there',
                'Message-Id' => 'mid-1',
            ],
            server: ['HTTP_X_BEACON_INBOUND_SECRET' => 'secret'],
        ));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('ignored', $response->getContent());
    }

    private function controller(bool $enabled, string $secret): InboundEmailController
    {
        return new InboundEmailController(
            $this->handler(),
            new NullLogger(),
            $this->opsDefaultsWith(static function ($settings) use ($enabled, $secret): void {
                $settings->setInboundEmailEnabled($enabled);
                $settings->setInboundWebhookSecret($secret);
            }),
        );
    }

    private function handler(): InboundEmailCommentHandler
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $replyToken = new InboundEmailReplyToken($this->opsDefaultsWith(static function (InstanceSettings $s): void {
            $s->setInboundWebhookSecret('secret');
        }));
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([]);
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issue');
        $settings = $this->createStub(InstanceSettingsRepository::class);
        $settings->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settings);
        $mailTransport = $this->createStub(IssueUserMailTransport::class);
        $mailTransport->method('isAvailable')->willReturn(false);
        $mentionParser = new IssueMentionParser($memberships);
        $commentCreator = new IssueCommentCreator(
            $em,
            new UserActionRecorder($em, new RequestStack()),
            new NotificationDispatcher(
                $destinations,
                $this->createStub(NotificationDigestBufferRepository::class),
                new NotificationPayloadBuilder($urls),
                new QuietHoursEvaluator(),
                new NotificationCircuitBreaker($ops),
                $this->createStub(MessageBusInterface::class),
                $em,
                $this->createStub(MemberIssueRealtimeNotifierInterface::class),
            ),
            new IssueUserMailNotifier(
                $mailTransport,
                $mentionParser,
                $this->createStub(TranslatorInterface::class),
                $urls,
                new NullLogger(),
                $replyToken,
                $ops,
            ),
            $mentionParser,
        );

        return new InboundEmailCommentHandler(
            $replyToken,
            new InboundEmailQuoteStripper(),
            $this->createStub(IssueRepository::class),
            $this->createStub(UserRepository::class),
            new ProjectAccessService(
                $memberships,
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
            $commentCreator,
            $this->createStub(InboundEmailMessageRepository::class),
            $em,
            new NullLogger(),
        );
    }
}
