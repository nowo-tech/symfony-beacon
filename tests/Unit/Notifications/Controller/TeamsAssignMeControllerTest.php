<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\InboundEmailReplyToken;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueAssigneeGuard;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMentionParser;
use App\Issues\Service\IssueUserMailNotifier;
use App\Issues\Service\IssueUserMailTransport;
use App\Notifications\Controller\TeamsAssignMeController;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\ActionTokenConsumer;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Notifications\Service\InteractionActionToken;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TeamsAssignMeControllerTest extends TestCase
{
    public function testIncompleteTokenDenied(): void
    {
        $controller = $this->controller();
        $this->expectException(AccessDeniedException::class);
        $controller(Request::create('/hooks/teams/assign-me'));
    }

    public function testUnknownDestinationDenied(): void
    {
        $controller = $this->controller(withDestination: false);
        $this->expectException(AccessDeniedException::class);
        $controller(Request::create('/hooks/teams/assign-me', parameters: [
            'd' => '11111111-1111-4111-8111-111111111111',
        ]));
    }

    public function testInvalidTokenDenied(): void
    {
        $controller = $this->controller(withDestination: true);
        $this->expectException(AccessDeniedException::class);
        $controller(Request::create('/hooks/teams/assign-me', parameters: [
            'a' => 'assign',
            'd' => '11111111-1111-4111-8111-111111111111',
            'p' => '22222222-2222-4222-8222-222222222222',
            'i' => '33333333-3333-4333-8333-333333333333',
            'n' => 'nonce',
            'exp' => (string) (time() + 3600),
            'sig' => 'bad',
        ]));
    }

    private function controller(bool $withDestination = false): TeamsAssignMeController
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $settings = $this->createStub(InstanceSettingsRepository::class);
        $settings->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settings);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/issues/x');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);
        if ($withDestination) {
            $project = new Project()->setName('Acme')->setSlug('acme');
            $destination = new NotificationDestination();
            $destination->setProject($project);
            $destination->setType(NotificationDestinationType::Teams);
            $destination->setSigningSecret('teams-secret');
            $destination->setEndpointUrl('https://outlook.office.com/webhook/x');
            $destination->setLabel('Ops');
            $destination->setCategories(['error']);
            $destinations->method('findOneBy')->willReturn($destination);
        } else {
            $destinations->method('findOneBy')->willReturn(null);
        }
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
        $token = new InteractionActionToken();

        $controller = new TeamsAssignMeController(
            $token,
            new HookDestinationContextResolver($destinations),
            new ActionTokenConsumer($token, new ArrayAdapter()),
            $this->createStub(IssueRepository::class),
            new IssueAssigneeChanger(
                $em,
                $history,
                new IssueAssigneeGuard($access),
                $recorder,
                $dispatcher,
                new IssueUserMailNotifier(
                    $mailTransport,
                    new IssueMentionParser($memberships),
                    $this->createStub(TranslatorInterface::class),
                    $urls,
                    new NullLogger(),
                    new InboundEmailReplyToken($ops),
                    $ops,
                ),
            ),
            $access,
            new NullLogger(),
        );

        $user = new User()->setEmail('assign@example.com');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $urls);
        $controller->setContainer($container);

        return $controller;
    }
}
