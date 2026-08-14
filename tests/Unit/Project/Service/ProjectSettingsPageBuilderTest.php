<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\EventRepository;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectGovernanceResolver;
use App\Project\Service\ProjectGroupAccessManager;
use App\Project\Service\ProjectMembershipFormSupport;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipPolicy;
use App\Project\Service\ProjectSettingsPageBuilder;
use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Health\MessengerQueueHealth;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectSettingsPageBuilderTest extends TestCase
{
    public function testBuildAssemblesOwnerSettingsPage(): void
    {
        $project = (new Project())->setName('Acme')->setSlug('acme');
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 5);
        $owner = (new User())->setEmail('owner@example.com');
        (new ReflectionProperty(User::class, 'id'))->setValue($owner, 1);
        $membership = (new ProjectMembership())->setProject($project)->setUser($owner)->setRole(ProjectRole::Owner);
        (new ReflectionProperty(ProjectMembership::class, 'id'))->setValue($membership, 21);
        $project->addMembership($membership);

        $formView = new FormView();
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $formFactory->method('createNamed')->willReturn($form);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/projects/x/settings');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('hydrateAccessGraph');
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $memberships->method('countOwnersByProjectIds')->willReturn([5 => 1]);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $accessService = new ProjectAccessService(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $memberships,
            $this->createStub(UserGroupMembershipRepository::class),
            $accessService,
            $auth,
        );
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $recorder = new UserActionRecorder($em, new RequestStack());

        $userGroups = $this->createStub(UserGroupRepository::class);
        $userGroups->method('findAllOrdered')->willReturn([]);
        $groupMemberships = $this->createStub(UserGroupMembershipRepository::class);
        $groupMemberships->method('countByGroupIds')->willReturn([]);
        $groupMemberships->method('findByUser')->willReturn([]);
        $attempts = $this->createStub(NotificationDeliveryAttemptRepository::class);
        $attempts->method('findRecentByDestinations')->willReturn([]);
        $readTokens = $this->createStub(ProjectReadTokenRepository::class);
        $readTokens->method('findByProject')->willReturn([]);
        $shareLinks = $this->createStub(ProjectShareLinkRepository::class);
        $shareLinks->method('findActiveByProject')->willReturn([]);

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settingsRepo);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(0);
        $events->method('countReceivedSinceForProject')->willReturn(0);
        $governance = new ProjectGovernanceResolver($events, $ops);

        $alertPrefs = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $alertPrefs->method('findIndexedByProjectIdForUser')->willReturn([]);
        $accountEvents = $this->createStub(MemberAccountAlertEventRepository::class);
        $accountEvents->method('findIndexedByEventForUser')->willReturn([]);
        $projectEvents = $this->createStub(MemberProjectAlertEventRepository::class);
        $projectEvents->method('findIndexedByProjectIdForUser')->willReturn([]);

        $builder = new ProjectSettingsPageBuilder(
            $formFactory,
            new CsrfOnlyFormFactory($formFactory),
            $urls,
            $auth,
            new MemberAlertPreferenceManager($alertPrefs, $accountEvents, $projectEvents, $em),
            $projects,
            $readTokens,
            $shareLinks,
            new HumanFriendlyTokenGenerator(),
            new ProjectMembershipManager(
                $this->createStub(UserRepository::class),
                $memberships,
                $policy,
                $recorder,
                $em,
            ),
            new ProjectGroupAccessManager($groups, $policy, $recorder, $em),
            new ProjectMembershipFormSupport($projects, $userGroups),
            $groupMemberships,
            $userGroups,
            $attempts,
            $governance,
            new MessengerQueueHealth($em),
            $memberships,
            $accessService,
        );

        $request = Request::create('https://beacon.test/projects/'.$project->getUuid().'/settings');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_beacon_last_share_url', 'https://beacon.test/share/x');
        $request->setSession($session);

        $access = new ProjectAccess(ProjectRole::Owner, directMembership: $membership);
        $page = $builder->build($project, $owner, $access, $request, ProjectSettingsSection::General);

        self::assertSame($project, $page['project']);
        self::assertSame($access, $page['access']);
        self::assertSame(ProjectSettingsSection::General, $page['settingsSection']);
        self::assertSame('https://beacon.test', $page['baseUrl']);
        self::assertSame(1, $page['ownerCount']);
        self::assertNotNull($page['governanceForm']);
        self::assertNotNull($page['apiKeyCreateForm']);
        self::assertNotNull($page['deleteProjectForm']);
        self::assertNotNull($page['transferOwnershipForm']);
        self::assertArrayHasKey(21, $page['memberRemoveForms']);
        self::assertSame('https://beacon.test/share/x', $page['lastShareUrl']);
        self::assertFalse($session->has('_beacon_last_share_url'));
        self::assertNotSame('', $page['suggestedLabel']);
    }
}
