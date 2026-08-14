<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueStatusChanger;
use App\Notifications\Controller\TeamsActionsController;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Service\ActionTokenConsumer;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Notifications\Service\HookMutationPolicy;
use App\Notifications\Service\InteractionActionToken;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TeamsActionsControllerTest extends TestCase
{
    public function testRejectsEmptyInvalidAndIncompleteBodies(): void
    {
        $controller = $this->controller();

        self::assertSame(Response::HTTP_BAD_REQUEST, $controller(Request::create('/hooks/teams/actions', 'POST'))->getStatusCode());
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create('/hooks/teams/actions', 'POST', content: '{'))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $controller(Request::create('/hooks/teams/actions', 'POST', content: '{"a":"resolve"}'))->getStatusCode(),
        );
    }

    public function testUnknownDestinationReturnsUnauthorized(): void
    {
        $controller = $this->controller();
        $response = $controller(Request::create(
            '/hooks/teams/actions',
            'POST',
            content: json_encode(['d' => '11111111-1111-4111-8111-111111111111'], \JSON_THROW_ON_ERROR),
        ));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Unknown destination', $response->getContent());
    }

    private function controller(): TeamsActionsController
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
        $token = new InteractionActionToken();

        return new TeamsActionsController(
            $token,
            new HookDestinationContextResolver($destinations),
            new ActionTokenConsumer($token, new ArrayAdapter()),
            $this->createStub(IssueRepository::class),
            new IssueStatusChanger(
                $em,
                new IssueHistoryRecorder($em),
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
            ),
            $em,
            new NullLogger(),
            new HookMutationPolicy($ops),
        );
    }
}
