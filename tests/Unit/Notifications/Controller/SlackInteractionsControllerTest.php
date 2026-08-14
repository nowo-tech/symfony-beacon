<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\InboundEmailReplyToken;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueAssigneeGuard;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMentionParser;
use App\Issues\Service\IssueStatusChanger;
use App\Issues\Service\IssueUserMailNotifier;
use App\Issues\Service\IssueUserMailTransport;
use App\Notifications\Controller\SlackInteractionsController;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Notifications\Service\HookMutationPolicy;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Notifications\Service\SlackRequestSignatureVerifier;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
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

final class SlackInteractionsControllerTest extends TestCase
{
    public function testRejectsMalformedInteractionPayloads(): void
    {
        $controller = $this->controller();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create('/hooks/slack/interactions', 'POST'))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create('/hooks/slack/interactions', 'POST', ['payload' => '{']))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create(
                '/hooks/slack/interactions',
                'POST',
                ['payload' => json_encode(['type' => 'url_verification'], \JSON_THROW_ON_ERROR)],
            ))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create(
                '/hooks/slack/interactions',
                'POST',
                ['payload' => json_encode(['actions' => []], \JSON_THROW_ON_ERROR)],
            ))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create(
                '/hooks/slack/interactions',
                'POST',
                ['payload' => json_encode(['actions' => [['value' => '']]], \JSON_THROW_ON_ERROR)],
            ))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create(
                '/hooks/slack/interactions',
                'POST',
                ['payload' => json_encode(['actions' => [['value' => '{']]], \JSON_THROW_ON_ERROR)],
            ))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create(
                '/hooks/slack/interactions',
                'POST',
                ['payload' => json_encode(['actions' => [['value' => json_encode(['a' => 'noop'], \JSON_THROW_ON_ERROR)]]], \JSON_THROW_ON_ERROR)],
            ))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create(
                '/hooks/slack/interactions',
                'POST',
                ['payload' => json_encode(['actions' => [['value' => json_encode(['a' => 'resolve', 'd' => '', 'p' => 'p', 'i' => 'i'], \JSON_THROW_ON_ERROR)]]], \JSON_THROW_ON_ERROR)],
            ))->getStatusCode(),
        );
    }

    public function testUnknownDestinationReturnsUnauthorized(): void
    {
        $controller = $this->controller();
        $value = json_encode([
            'a' => 'resolve',
            'd' => '11111111-1111-4111-8111-111111111111',
            'p' => '22222222-2222-4222-8222-222222222222',
            'i' => '33333333-3333-4333-8333-333333333333',
        ], \JSON_THROW_ON_ERROR);
        $response = $controller(Request::create(
            '/hooks/slack/interactions',
            'POST',
            ['payload' => json_encode(['actions' => [['value' => $value]]], \JSON_THROW_ON_ERROR)],
        ));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    private function controller(): SlackInteractionsController
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $settings = $this->createStub(InstanceSettingsRepository::class);
        $settings->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settings);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/i');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);
        $destinations->method('findOneBy')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $access = new ProjectAccessService(
            $memberships,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $dispatcher = new NotificationDispatcher(
            $destinations,
            $this->createStub(NotificationDigestBufferRepository::class),
            new NotificationPayloadBuilder($urls),
            new QuietHoursEvaluator(),
            new NotificationCircuitBreaker($ops),
            $this->createStub(MessageBusInterface::class),
            $em,
            $this->createStub(MemberIssueRealtimeNotifierInterface::class),
        );
        $history = new IssueHistoryRecorder($em);
        $recorder = new UserActionRecorder($em, new RequestStack());
        $mailTransport = $this->createStub(IssueUserMailTransport::class);
        $mailTransport->method('isAvailable')->willReturn(false);
        $mentionParser = new IssueMentionParser($memberships);
        $assigneeChanger = new IssueAssigneeChanger(
            $em,
            $history,
            new IssueAssigneeGuard($access),
            $recorder,
            $dispatcher,
            new IssueUserMailNotifier(
                $mailTransport,
                $mentionParser,
                $this->createStub(TranslatorInterface::class),
                $urls,
                new NullLogger(),
                new InboundEmailReplyToken($ops),
                $ops,
            ),
        );

        return new SlackInteractionsController(
            new SlackRequestSignatureVerifier(),
            new HookDestinationContextResolver($destinations),
            $this->createStub(IssueRepository::class),
            $this->createStub(UserRepository::class),
            new IssueStatusChanger($em, $history, $recorder, $dispatcher),
            $assigneeChanger,
            $access,
            $em,
            new NullLogger(),
            new HookMutationPolicy($ops),
        );
    }
}
