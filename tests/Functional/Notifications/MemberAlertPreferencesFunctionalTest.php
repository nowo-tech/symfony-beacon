<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notifications;

use App\Identity\Entity\User;
use App\Notifications\Entity\PushSubscription;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use App\Twig\Components\MemberAlertPreferencesLive;
use App\Twig\Components\MemberProjectAlertPreferencesLive;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class MemberAlertPreferencesFunctionalTest extends DatabaseWebTestCase
{
    use InteractsWithLiveComponents;

    public function testNotificationsPageShowsLiveComponentAndCascadesMasterOff(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('member-alerts-ui@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/display/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="member-alert-preferences-live"]');
        self::assertSelectorExists('[data-testid="member-alert-preferences-form"]');
        self::assertSelectorExists('[data-testid="member-alerts-events"]');
        self::assertSelectorExists('[data-testid="member-alerts-projects-hint"]');
        self::assertSelectorExists(\sprintf('[data-project-uuid="%s"]', $project->getUuid()));

        $component = $this->createLiveComponent(
            name: MemberAlertPreferencesLive::class,
            data: [
                'initialFormData' => [
                    'memberAlertsEnabled' => true,
                    'events' => MemberAlertEvent::mapEventsToFormKeys(
                        array_fill_keys(
                            array_map(static fn (MemberAlertEvent $e): string => $e->value, MemberAlertEvent::casesInUiOrder()),
                            ['enabled' => true, 'scope' => 'all'],
                        ),
                    ),
                    'pushNotificationsEnabled' => false,
                ],
                'pushAvailable' => false,
                'projects' => [[
                    'uuid' => $project->getUuid(),
                    'name' => $project->getName(),
                    'hasOverrides' => false,
                    'formData' => [
                        'enabled' => true,
                        'resetOverrides' => false,
                        'events' => MemberAlertEvent::mapEventsToFormKeys(
                            array_fill_keys(
                                array_map(static fn (MemberAlertEvent $e): string => $e->value, MemberAlertEvent::casesInUiOrder()),
                                ['enabled' => true, 'scope' => 'all'],
                            ),
                        ),
                    ],
                ]],
            ],
            client: $client,
        )->actingAs($user);

        self::assertStringContainsString('member-alerts-events', $component->render()->toString());

        $component->set('member_alert_preferences.memberAlertsEnabled', false);
        $html = $component->render()->toString();
        self::assertStringContainsString('member-alerts-master-off-hint', $html);
        self::assertStringNotContainsString('data-testid="member-alerts-events"', $html);

        // Re-enable master and turn off a single event: scope (all/involved) must hide.
        $component->set('member_alert_preferences.memberAlertsEnabled', true);
        $html = $component->render()->toString();
        self::assertStringContainsString('data-testid="member-alerts-events"', $html);
        self::assertStringContainsString('data-event-key="issue_new"', $html);

        $component->set('member_alert_preferences.events.issue_new.enabled', null);
        $html = $component->render()->toString();
        self::assertMatchesRegularExpression(
            '/data-event-key="issue_new"[\s\S]*?<\/li>/',
            $html,
            'Expected issue_new row in rendered HTML',
        );
        preg_match('/data-event-key="issue_new"[\s\S]*?<\/li>/', $html, $m);
        self::assertStringNotContainsString(
            'data-testid="member-alert-event-scope"',
            $m[0] ?? '',
            'Scope switch must hide when event notify is off',
        );
    }

    public function testProjectSettingsShowsMemberAlertsForm(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('member-alerts-project-ui@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/alerts');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="project-member-alerts"]');
        self::assertSelectorExists('[data-testid="member-project-alert-preferences-live"]');
        self::assertSelectorExists('[data-testid="member-alerts-project-form"]');
        self::assertSelectorExists('[data-testid="member-alerts-project-overrides"]');

        $component = $this->createLiveComponent(
            name: MemberProjectAlertPreferencesLive::class,
            data: [
                'projectUuid' => $project->getUuid(),
                'projectName' => $project->getName(),
                'initialFormData' => [
                    'enabled' => true,
                    'resetOverrides' => false,
                    'events' => MemberAlertEvent::mapEventsToFormKeys(
                        array_fill_keys(
                            array_map(static fn (MemberAlertEvent $e): string => $e->value, MemberAlertEvent::casesInUiOrder()),
                            ['enabled' => true, 'scope' => 'all'],
                        ),
                    ),
                ],
                'returnTo' => 'project',
                'showModalActions' => false,
            ],
            client: $client,
        )->actingAs($user);

        $formName = 'project_alerts_'.str_replace('-', '', $project->getUuid());
        $component->set($formName.'.enabled', false);
        $html = $component->render()->toString();
        self::assertStringContainsString('member-alerts-project-off-hint', $html);
        self::assertStringNotContainsString('data-testid="member-alerts-project-overrides"', $html);
    }

    public function testSaveMasterOffViaLiveComponentPersists(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('member-alerts-save@example.com');
        $this->login($client, $user);

        $events = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $events[$event->value] = ['enabled' => true, 'scope' => 'all'];
        }

        $component = $this->createLiveComponent(
            name: MemberAlertPreferencesLive::class,
            data: [
                'initialFormData' => [
                    'memberAlertsEnabled' => true,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                    'pushNotificationsEnabled' => false,
                ],
                'pushAvailable' => false,
                'projects' => [],
            ],
            client: $client,
        )->actingAs($user);

        $formValues = [
            'member_alert_preferences' => [
                'memberAlertsEnabled' => false,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $formValues['member_alert_preferences']['events'][$event->formKey()] = [
                'enabled' => true,
                'involved' => false,
            ];
        }

        $component->submitForm($formValues, 'save');
        self::assertResponseRedirects('/account/display/notifications');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->find($user::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isMemberAlertsEnabled());

        $projectComponent = $this->createLiveComponent(
            name: MemberProjectAlertPreferencesLive::class,
            data: [
                'projectUuid' => $project->getUuid(),
                'projectName' => $project->getName(),
                'initialFormData' => [
                    'enabled' => true,
                    'resetOverrides' => false,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                ],
            ],
            client: $client,
        )->actingAs($user);

        $projectFormName = 'project_alerts_'.str_replace('-', '', $project->getUuid());
        $projectValues = [
            $projectFormName => [
                'enabled' => false,
                'resetOverrides' => false,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $projectValues[$projectFormName]['events'][$event->formKey()] = [
                'enabled' => true,
                'involved' => false,
            ];
        }
        $projectComponent->submitForm($projectValues, 'save');
        self::assertResponseRedirects('/account/display/notifications');

        $em->clear();
        $reloaded = $em->find($user::class, $user->getId());
        self::assertNotNull($reloaded);
        $projectPrefRepo = self::getContainer()->get(MemberProjectAlertPreferenceRepository::class);
        $pref = $projectPrefRepo->findOneByUserAndProject($reloaded, $em->find($project::class, $project->getId()));
        self::assertNotNull($pref);
        self::assertFalse($pref->isEnabled());
    }

    public function testViewerCanSaveOwnProjectPrefsFromAccountButCannotOpenSettings(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('member-alerts-viewer-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $viewer = new User();
        $viewer->setEmail('member-alerts-viewer@example.com');
        $viewer->setDisplayName('Viewer');
        $viewer->setPassword($hasher->hashPassword($viewer, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($viewer);
        $membership->setRole(ProjectRole::Viewer);
        $project->addMembership($membership);
        $em->persist($viewer);
        $em->flush();

        $this->login($client, $viewer);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/alerts');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request(Request::METHOD_GET, '/account/display/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('[data-project-uuid="%s"]', $project->getUuid()));

        $events = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $events[$event->value] = ['enabled' => true, 'scope' => 'all'];
        }

        $projectComponent = $this->createLiveComponent(
            name: MemberProjectAlertPreferencesLive::class,
            data: [
                'projectUuid' => $project->getUuid(),
                'projectName' => $project->getName(),
                'initialFormData' => [
                    'enabled' => true,
                    'resetOverrides' => false,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                ],
                'returnTo' => 'account',
            ],
            client: $client,
        )->actingAs($viewer);

        $projectFormName = 'project_alerts_'.str_replace('-', '', $project->getUuid());
        $projectValues = [
            $projectFormName => [
                'enabled' => false,
                'resetOverrides' => false,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $projectValues[$projectFormName]['events'][$event->formKey()] = [
                'enabled' => true,
                'involved' => false,
            ];
        }
        $projectComponent->submitForm($projectValues, 'save');
        self::assertResponseRedirects('/account/display/notifications');

        $em->clear();
        $reloadedViewer = $em->find($viewer::class, $viewer->getId());
        self::assertNotNull($reloadedViewer);
        $reloadedProject = $em->find($project::class, $project->getId());
        self::assertNotNull($reloadedProject);
        $projectPrefRepo = self::getContainer()->get(MemberProjectAlertPreferenceRepository::class);
        $pref = $projectPrefRepo->findOneByUserAndProject($reloadedViewer, $reloadedProject);
        self::assertNotNull($pref);
        self::assertFalse($pref->isEnabled());
    }

    public function testSaveAccountEventOptOutCreatesRow(): void
    {
        [$client, $user] = $this->bootWithDemoProject('member-alerts-event@example.com');
        $this->login($client, $user);

        $events = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $events[$event->value] = ['enabled' => true, 'scope' => 'all'];
        }

        $component = $this->createLiveComponent(
            name: MemberAlertPreferencesLive::class,
            data: [
                'initialFormData' => [
                    'memberAlertsEnabled' => true,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                    'pushNotificationsEnabled' => false,
                ],
                'pushAvailable' => false,
                'projects' => [],
            ],
            client: $client,
        )->actingAs($user);

        $formValues = [
            'member_alert_preferences' => [
                'memberAlertsEnabled' => true,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $formValues['member_alert_preferences']['events'][$event->formKey()] = [
                'enabled' => MemberAlertEvent::IssueAssigned !== $event,
                'involved' => false,
            ];
        }

        $component->submitForm($formValues, 'save');
        self::assertResponseRedirects('/account/display/notifications');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $reloaded = $em->find($user::class, $user->getId());
        self::assertNotNull($reloaded);

        $accountRepo = self::getContainer()->get(MemberAccountAlertEventRepository::class);
        $row = $accountRepo->findOneByUserAndEvent($reloaded, MemberAlertEvent::IssueAssigned);
        self::assertNotNull($row);
        self::assertFalse($row->isEnabled());
    }

    public function testSaveDisablesPushAndRedirectsProjectSettingsReturnTo(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('member-alerts-push-off@example.com');
        $this->login($client, $user);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setPushNotificationsEnabled(true);
        $em->flush();

        $events = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $events[$event->value] = ['enabled' => true, 'scope' => 'all'];
        }

        $component = $this->createLiveComponent(
            name: MemberAlertPreferencesLive::class,
            data: [
                'initialFormData' => [
                    'memberAlertsEnabled' => true,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                    'pushNotificationsEnabled' => true,
                ],
                'pushAvailable' => true,
                'projects' => [],
            ],
            client: $client,
        )->actingAs($user);

        $formValues = [
            'member_alert_preferences' => [
                'memberAlertsEnabled' => true,
                'pushNotificationsEnabled' => false,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $formValues['member_alert_preferences']['events'][$event->formKey()] = [
                'enabled' => true,
                'involved' => false,
            ];
        }
        $component->submitForm($formValues, 'save');
        self::assertResponseRedirects('/account/display/notifications');

        $em->clear();
        $reloaded = $em->find($user::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isPushNotificationsEnabled());

        $projectComponent = $this->createLiveComponent(
            name: MemberProjectAlertPreferencesLive::class,
            data: [
                'projectUuid' => $project->getUuid(),
                'projectName' => $project->getName(),
                'initialFormData' => [
                    'enabled' => true,
                    'resetOverrides' => false,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                ],
                'returnTo' => 'project',
            ],
            client: $client,
        )->actingAs($user);

        $projectFormName = 'project_alerts_'.str_replace('-', '', $project->getUuid());
        $projectValues = [
            $projectFormName => [
                'enabled' => true,
                'resetOverrides' => false,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $projectValues[$projectFormName]['events'][$event->formKey()] = [
                'enabled' => true,
                'involved' => MemberAlertEvent::IssueCommented === $event,
            ];
        }
        $projectComponent->submitForm($projectValues, 'save');
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings/alerts#member-alerts');
    }

    public function testSaveRemovesPushSubscriptionsAndUnknownProjectReturns404(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('member-alerts-push-sub@example.com');
        $this->login($client, $user);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setPushNotificationsEnabled(true);
        $sub = new PushSubscription($user);
        $sub->setSubscription('https://fcm.googleapis.com/fcm/send/test-endpoint', 'p256', 'auth', 'aesgcm');
        $em->persist($sub);
        $em->flush();

        $events = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $events[$event->value] = ['enabled' => true, 'scope' => 'all'];
        }

        $component = $this->createLiveComponent(
            name: MemberAlertPreferencesLive::class,
            data: [
                'initialFormData' => [
                    'memberAlertsEnabled' => true,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                    'pushNotificationsEnabled' => true,
                ],
                'pushAvailable' => true,
                'projects' => [],
            ],
            client: $client,
        )->actingAs($user);

        $formValues = [
            'member_alert_preferences' => [
                'memberAlertsEnabled' => true,
                'pushNotificationsEnabled' => false,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $formValues['member_alert_preferences']['events'][$event->formKey()] = [
                'enabled' => true,
                'involved' => false,
            ];
        }
        $component->submitForm($formValues, 'save');
        self::assertResponseRedirects('/account/display/notifications');
        self::assertSame([], self::getContainer()->get(PushSubscriptionRepository::class)->findByUser($user));

        $missing = $this->createLiveComponent(
            name: MemberProjectAlertPreferencesLive::class,
            data: [
                'projectUuid' => '00000000-0000-4000-8000-000000000099',
                'projectName' => 'Gone',
                'initialFormData' => [
                    'enabled' => true,
                    'resetOverrides' => false,
                    'events' => MemberAlertEvent::mapEventsToFormKeys($events),
                ],
                'returnTo' => 'account',
            ],
            client: $client,
        )->actingAs($user);

        $formName = 'project_alerts_'.str_replace('-', '', '00000000-0000-4000-8000-000000000099');
        $values = [
            $formName => [
                'enabled' => true,
                'resetOverrides' => false,
                'events' => [],
            ],
        ];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $values[$formName]['events'][$event->formKey()] = [
                'enabled' => true,
                'involved' => false,
            ];
        }
        try {
            $missing->submitForm($values, 'save');
            self::fail('Expected NotFoundHttpException for unknown project');
        } catch (NotFoundHttpException $e) {
            self::assertSame('Project not found.', $e->getMessage());
        }
    }
}
