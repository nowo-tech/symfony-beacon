<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\EventRepository;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Entity\ProjectReadToken;
use App\Project\Entity\ProjectShareLink;
use App\Project\Enum\ProjectRole;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectGovernanceResolver;
use App\Project\Service\ProjectGroupAccessManager;
use App\Project\Service\ProjectMembershipFormSupport;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipPolicy;
use App\Project\Service\ProjectSettingsPageBuilder;
use App\Shared\Health\MessengerQueueHealth;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use App\Tests\Support\ProjectAccessServiceFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
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
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $owner = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($owner, 1);
        $membership = new ProjectMembership()->setProject($project)->setUser($owner)->setRole(ProjectRole::Owner);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 21);
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
        $accessService = ProjectAccessServiceFactory::create(
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

    public function testBuildExposesMatchedApiKeyDsnAndSkipsUnsavedRows(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $owner = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($owner, 1);
        $membership = new ProjectMembership()->setProject($project)->setUser($owner)->setRole(ProjectRole::Owner);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 21);
        $project->addMembership($membership);
        $project->addMembership(new ProjectMembership()->setProject($project)->setUser($owner)->setRole(ProjectRole::Member));

        $apiKey = ProjectApiKey::generate($project, 'Primary', 'public-one', 'secret-one');
        new ReflectionProperty(ProjectApiKey::class, 'id')->setValue($apiKey, 7);
        $project->addApiKey($apiKey);
        $project->addApiKey(ProjectApiKey::generate($project, 'Unsaved', 'public-two', 'secret-two'));

        $group = new UserGroup()->setName('Ops')->setSlug('ops');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, 88);
        $groupAccess = new ProjectGroupAccess()->setProject($project)->setUserGroup($group)->setRole(ProjectRole::Member);
        new ReflectionProperty(ProjectGroupAccess::class, 'id')->setValue($groupAccess, 77);
        $project->addGroupAccess($groupAccess);
        $project->addGroupAccess(new ProjectGroupAccess()->setProject($project)->setUserGroup($group)->setRole(ProjectRole::Admin));

        $destination = new NotificationDestination()
            ->setProject($project)
            ->setLabel('Slack')
            ->setType(NotificationDestinationType::Slack)
            ->setEndpointUrl('https://hooks.slack.com/services/T/B/X');
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($destination, 55);
        $project->addNotificationDestination($destination);
        $project->addNotificationDestination(
            new NotificationDestination()
                ->setProject($project)
                ->setLabel('Unsaved')
                ->setType(NotificationDestinationType::Email)
                ->setEndpointUrl('ops@example.com'),
        );

        $threshold = new ProjectThresholdRule()->setProject($project)->setLabel('Burst');
        new ReflectionProperty(ProjectThresholdRule::class, 'id')->setValue($threshold, 66);
        $project->addThresholdRule($threshold);
        $project->addThresholdRule(new ProjectThresholdRule()->setProject($project)->setLabel('Unsaved threshold'));

        $savedReadToken = new ProjectReadToken()->setProject($project)->setCreatedBy($owner)->setLabel('Reader')->setPrefix('brt_demo')->setTokenHash('hash');
        new ReflectionProperty(ProjectReadToken::class, 'id')->setValue($savedReadToken, 33);
        $unsavedReadToken = new ProjectReadToken()->setProject($project)->setCreatedBy($owner)->setLabel('Unsaved')->setPrefix('brt_unsaved')->setTokenHash('hash');

        $savedShare = new ProjectShareLink()->setProject($project)->setCreatedBy($owner)->setTokenHash('hash');
        new ReflectionProperty(ProjectShareLink::class, 'id')->setValue($savedShare, 44);
        $unsavedShare = new ProjectShareLink()->setProject($project)->setCreatedBy($owner)->setTokenHash('hash');

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
        $auth->method('isGranted')->willReturn(true);
        $accessService = ProjectAccessServiceFactory::create(
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
        $userGroups->method('findAllOrdered')->willReturn([$group, new UserGroup()]);
        $groupMemberships = $this->createStub(UserGroupMembershipRepository::class);
        $groupMemberships->method('countByGroupIds')->willReturn([88 => 3]);
        $groupMemberships->method('findByUser')->willReturn([]);
        $attempts = $this->createStub(NotificationDeliveryAttemptRepository::class);
        $attempts->method('findRecentByDestinations')->willReturn([55 => []]);
        $readTokens = $this->createStub(ProjectReadTokenRepository::class);
        $readTokens->method('findByProject')->willReturn([$savedReadToken, $unsavedReadToken]);
        $shareLinks = $this->createStub(ProjectShareLinkRepository::class);
        $shareLinks->method('findActiveByProject')->willReturn([$savedShare, $unsavedShare]);

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settingsRepo);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(5);
        $events->method('countReceivedSinceForProject')->willReturn(9);
        $governance = new ProjectGovernanceResolver($events, $ops);

        $alertPrefs = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $alertPrefs->method('findIndexedByProjectIdForUser')->willReturn([['enabled' => false, 'events' => [], 'hasOverrides' => true]]);
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
        $session->set('_beacon_last_api_key_dsn', 'https://public-one:secret-one@beacon.test/'.$project->getUuid());
        $session->set('_beacon_last_share_url', 'https://beacon.test/share/new');
        $request->setSession($session);

        $access = new ProjectAccess(ProjectRole::Owner, directMembership: $membership);
        $page = $builder->build($project, $owner, $access, $request, ProjectSettingsSection::Access);

        self::assertSame([88 => 3], $page['group_member_counts']);
        self::assertSame(5, $page['eventsToday']);
        self::assertSame(9, $page['eventsThisMonth']);
        self::assertSame('https://public-one:secret-one@beacon.test/'.$project->getUuid(), $page['apiKeyDsns'][7]);
        self::assertStringContainsString('public-one:••••••••@beacon.test', $page['apiKeyMaskedDsns'][7]);
        self::assertNull($page['lastApiKeyDsn']);
        self::assertArrayHasKey(77, $page['groupRoleForms']);
        self::assertArrayHasKey(77, $page['groupRemoveForms']);
        self::assertArrayHasKey(33, $page['readTokenRevokeForms']);
        self::assertArrayHasKey(44, $page['shareRevokeForms']);
        self::assertArrayHasKey(55, $page['notificationResumeForms']);
        self::assertArrayHasKey(55, $page['notificationToggleForms']);
        self::assertArrayHasKey(55, $page['notificationTestForms']);
        self::assertArrayHasKey(55, $page['notificationDeleteForms']);
        self::assertArrayHasKey(66, $page['thresholdToggleForms']);
        self::assertArrayHasKey(66, $page['thresholdDeleteForms']);
        self::assertArrayHasKey('enabled', $page['memberAlertsInitial']);
        self::assertArrayHasKey('memberAlertsHasOverrides', $page);
        self::assertSame('https://beacon.test/share/new', $page['lastShareUrl']);
    }

    public function testBuildFiltersGroupsForLimitedMemberAccess(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 9);
        $memberUser = new User()->setEmail('member@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($memberUser, 11);
        $membership = new ProjectMembership()->setProject($project)->setUser($memberUser)->setRole(ProjectRole::Member);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 31);
        $project->addMembership($membership);

        $allowedGroup = new UserGroup()->setName('Allowed')->setSlug('allowed');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($allowedGroup, 51);
        $forbiddenGroup = new UserGroup()->setName('Forbidden')->setSlug('forbidden');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($forbiddenGroup, 52);
        $actorGroupMembership = new UserGroupMembership()
            ->setUser($memberUser)
            ->setUserGroup($allowedGroup);

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
        $memberships->method('countOwnersByProjectIds')->willReturn([9 => 1]);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $accessService = ProjectAccessServiceFactory::create(
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
        $userGroups->method('findAllOrdered')->willReturn([$allowedGroup, $forbiddenGroup, new UserGroup()]);
        $groupMemberships = $this->createStub(UserGroupMembershipRepository::class);
        $groupMemberships->method('countByGroupIds')->willReturn([51 => 2]);
        $groupMemberships->method('findByUser')->willReturn([$actorGroupMembership]);
        $attempts = $this->createStub(NotificationDeliveryAttemptRepository::class);
        $attempts->method('findRecentByDestinations')->willReturn([]);
        $readTokens = $this->createStub(ProjectReadTokenRepository::class);
        $readTokens->method('findByProject')->willReturn([]);
        $shareLinks = $this->createStub(ProjectShareLinkRepository::class);
        $shareLinks->method('findActiveByProject')->willReturn([]);

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $governance = new ProjectGovernanceResolver(
            $this->createStub(EventRepository::class),
            new InstanceOpsDefaults($settingsRepo),
        );

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

        $request = Request::create('https://beacon.test/projects/'.$project->getUuid().'/settings/access');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_beacon_last_read_token', 'brt_secret_once');
        $session->set('_beacon_last_share_url', 'https://beacon.test/share/member');
        $request->setSession($session);

        $access = new ProjectAccess(ProjectRole::Member, directMembership: $membership);
        $page = $builder->build($project, $memberUser, $access, $request, ProjectSettingsSection::Access);

        self::assertSame([$allowedGroup], $page['availableGroups']);
        self::assertSame([51 => 2], $page['group_member_counts']);
        self::assertSame([ProjectSettingsSection::Access, ProjectSettingsSection::Alerts], $page['settingsSections']);
        self::assertNull($page['governanceForm']);
        self::assertNull($page['apiKeyCreateForm']);
        self::assertNull($page['memberAddForm']);
        self::assertNull($page['shareCreateForm']);
        self::assertNull($page['readTokenCreateForm']);
        self::assertNull($page['groupAddForm']);
        self::assertNull($page['configImportForm']);
        self::assertNull($page['transferOwnershipForm']);
        self::assertNull($page['clearHistoryForm']);
        self::assertNull($page['deleteProjectForm']);
        self::assertSame('brt_secret_once', $page['lastReadToken']);
        self::assertSame('https://beacon.test/share/member', $page['lastShareUrl']);
        self::assertFalse($session->has('_beacon_last_read_token'));
        self::assertFalse($session->has('_beacon_last_share_url'));
    }

    public function testBuildCreatesOptionalFormsAndHandlesUnsavedProjectState(): void
    {
        $project = new Project()->setName('Unsaved')->setSlug('unsaved');
        $owner = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($owner, 1);
        $membership = new ProjectMembership()->setProject($project)->setUser($owner)->setRole(ProjectRole::Owner);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 21);
        $project->addMembership($membership);

        $apiKey = ProjectApiKey::generate($project, 'Primary', 'public-one', 'secret-one');
        new ReflectionProperty(ProjectApiKey::class, 'id')->setValue($apiKey, 7);
        $project->addApiKey($apiKey);

        $availableGroup = new UserGroup()->setName('Available')->setSlug('available');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($availableGroup, 88);

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
        $memberships->method('countOwnersByProjectIds')->willReturn([]);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $accessService = ProjectAccessServiceFactory::create(
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

        $alertPrefs = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $alertPrefs->method('findIndexedByProjectIdForUser')->willReturn([]);
        $accountEvents = $this->createStub(MemberAccountAlertEventRepository::class);
        $accountEvents->method('findIndexedByEventForUser')->willReturn([]);
        $projectEvents = $this->createStub(MemberProjectAlertEventRepository::class);
        $projectEvents->method('findIndexedByProjectIdForUser')->willReturn([]);
        $memberAlertPreferenceManager = new MemberAlertPreferenceManager($alertPrefs, $accountEvents, $projectEvents, $em);
        $membershipManager = new ProjectMembershipManager(
            $this->createStub(UserRepository::class),
            $memberships,
            $policy,
            $recorder,
            $em,
        );
        $groupAccessManager = new ProjectGroupAccessManager($groups, $policy, $recorder, $em);

        $userGroups = $this->createStub(UserGroupRepository::class);
        $userGroups->method('findAllOrdered')->willReturn([$availableGroup]);
        $groupMemberships = $this->createStub(UserGroupMembershipRepository::class);
        $groupMemberships->method('countByGroupIds')->willReturn([88 => 2]);
        $groupMemberships->method('findByUser')->willReturn([]);
        $attempts = $this->createStub(NotificationDeliveryAttemptRepository::class);
        $attempts->method('findRecentByDestinations')->willReturn([]);
        $readTokens = $this->createStub(ProjectReadTokenRepository::class);
        $readTokens->method('findByProject')->willReturn([]);
        $shareLinks = $this->createStub(ProjectShareLinkRepository::class);
        $shareLinks->method('findActiveByProject')->willReturn([]);

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedTodayForProject')->willReturn(0);
        $events->method('countReceivedSinceForProject')->willReturn(0);
        $governanceResolver = new ProjectGovernanceResolver($events, new InstanceOpsDefaults($settingsRepo));

        $builder = new ProjectSettingsPageBuilder(
            $formFactory,
            new CsrfOnlyFormFactory($formFactory),
            $urls,
            $auth,
            $memberAlertPreferenceManager,
            $projects,
            $readTokens,
            $shareLinks,
            new HumanFriendlyTokenGenerator(),
            $membershipManager,
            $groupAccessManager,
            new ProjectMembershipFormSupport($projects, $userGroups),
            $groupMemberships,
            $userGroups,
            $attempts,
            $governanceResolver,
            new MessengerQueueHealth($em),
            $memberships,
            $accessService,
        );

        $request = Request::create('https://beacon.test/projects/'.$project->getUuid().'/settings/access');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('_beacon_last_api_key_dsn', 'not-a-dsn');
        $request->setSession($session);

        $access = new ProjectAccess(ProjectRole::Owner, directMembership: $membership);
        $page = $builder->build($project, $owner, $access, $request, ProjectSettingsSection::Access);

        self::assertSame(0, $page['ownerCount']);
        self::assertSame('not-a-dsn', $page['lastApiKeyDsn']);
        self::assertNotNull($page['governanceForm']);
        self::assertNotNull($page['apiKeyCreateForm']);
        self::assertNotNull($page['memberAddForm']);
        self::assertNotNull($page['shareCreateForm']);
        self::assertNotNull($page['readTokenCreateForm']);
        self::assertNotNull($page['groupAddForm']);
        self::assertNotNull($page['configImportForm']);
        self::assertNotNull($page['transferOwnershipForm']);
        self::assertNotNull($page['clearHistoryForm']);
        self::assertNotNull($page['deleteProjectForm']);
        self::assertSame([$availableGroup], $page['availableGroups']);
    }
}
