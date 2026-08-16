<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Service\AccountDataExporter;
use App\Identity\Service\AccountSocialAccounts;
use App\Identity\UserActionType;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectMembershipRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Nowo\AuthKitBundle\Entity\SocialLoginAccount;
use Nowo\AuthKitBundle\Profile\ProfileRegistry;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class AccountDataExporterTest extends TestCase
{
    public function testExportBuildsScrubbedDocument(): void
    {
        $user = new User();
        $user->setEmail('export@example.com');
        $user->setDisplayName('Exporter');
        $user->setPreferredLocale('fr');
        $idProp = new ReflectionProperty(User::class, 'id');
        $idProp->setValue($user, 9);

        $social = new SocialLoginAccount();
        $social->setProvider('github');
        $social->setEmail('gh@example.com');

        $socialRepo = $this->createStub(SocialLoginAccountRepository::class);
        $socialRepo->method('findBy')->willReturn([$social]);

        $projects = $this->createStub(ProjectMembershipRepository::class);
        $projects->method('findByUser')->willReturn([]);

        $groups = $this->createStub(UserGroupMembershipRepository::class);
        $groups->method('findByUser')->willReturn([]);

        $actions = $this->createStub(UserActionRepository::class);
        $actions->method('findForUser')->willReturn([]);

        $push = $this->createStub(PushSubscriptionRepository::class);
        $push->method('findByUser')->willReturn([new stdClass(), new stdClass()]);

        $exporter = new AccountDataExporter(
            $projects,
            $groups,
            $actions,
            $push,
            new AccountSocialAccounts($this->gate(), $socialRepo),
        );

        $document = $exporter->export($user);

        self::assertSame('beacon-account-export/v1', $document['schema']);
        self::assertIsString($document['exported_at']);
        self::assertSame('export@example.com', $document['account']['email']);
        self::assertSame('Exporter', $document['account']['display_name']);
        self::assertSame('fr', $document['account']['preferred_locale']);
        self::assertSame([], $document['project_memberships']);
        self::assertSame([], $document['group_memberships']);
        self::assertSame([], $document['security_activity']);
        self::assertSame(0, $document['password_history']['count']);
        self::assertCount(1, $document['social_accounts']);
        self::assertSame('github', $document['social_accounts'][0]['provider']);
        self::assertSame('gh@example.com', $document['social_accounts'][0]['provider_email']);
        self::assertSame(2, $document['push_subscriptions_count']);
        self::assertArrayHasKey('events_retention', $document['notes']);
    }

    private function gate(): SocialLoginGate
    {
        $credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $credentials->method('findEnabledOrdered')->willReturn([]);

        return new SocialLoginGate(new ProfileRegistry([
            'main' => [
                'user_class' => User::class,
                'user_identifier_field' => 'email',
                'registration_role' => 'ROLE_USER',
                'registration_mode' => 'first_user_only',
                'login_fields' => [],
                'remember_me' => ['enabled' => false],
                'password_strength' => [],
                'registration_fields' => [],
                'templates' => [],
                'css' => ['button_class' => 'a', 'secondary_button_class' => 'b'],
                'embed' => ['mode' => 'disabled'],
                'password_reset' => [],
                'magic_login' => [],
                'social_login' => ['mode' => 'disabled'],
                'qr_login' => ['mode' => 'disabled'],
                'routes' => [
                    'login' => ['path' => '/login', 'name' => 'l'],
                    'logout' => ['path' => '/logout', 'name' => 'lo'],
                    'register' => ['path' => '/register', 'name' => 'r'],
                    'reset_password_request' => ['path' => '/r', 'name' => 'rp'],
                    'reset_password' => ['path' => '/rr', 'name' => 'rrr'],
                    'reset_password_code' => ['path' => '/rc', 'name' => 'rc'],
                    'magic_login_request' => ['path' => '/m', 'name' => 'm'],
                    'magic_login_check' => ['path' => '/mc', 'name' => 'mc'],
                    'social_login_start' => ['path' => '/s', 'name' => 's'],
                    'social_login_check' => ['path' => '/sc', 'name' => 'sc'],
                    'qr_login_start' => ['path' => '/q', 'name' => 'q'],
                    'qr_login_show' => ['path' => '/qs', 'name' => 'qs'],
                    'qr_login_status' => ['path' => '/qst', 'name' => 'qst'],
                    'qr_login_complete' => ['path' => '/qc', 'name' => 'qc'],
                    'qr_login_approve' => ['path' => '/qa', 'name' => 'qa'],
                    'qr_login_deny' => ['path' => '/qd', 'name' => 'qd'],
                ],
                'firewall' => 'main',
                'login_success_route' => 'home',
            ],
        ], 'main'), $credentials);
    }

    public function testExportIncludesMembershipsGroupsActivityHistoryAndRoles(): void
    {
        $user = new User();
        $user->setEmail('full@example.com');
        $user->setDisplayName('Full Export');
        $user->setSlackUserId('U999');
        $user->setPhone('+34600111222');
        $user->setPhoneVerifiedAt(new DateTimeImmutable('2024-01-02T03:04:05+00:00'));
        $user->setPreferredTheme('dark');
        $user->setPreferredContentWidth('full');
        $user->setPreferredUiDensity('compact');
        $user->setPreferredMotion('reduce');
        $user->setPreferredFontScale('lg');
        $user->setPreferredContrast('more');
        $user->setPreferredSidebar('collapsed');
        $user->setPushNotificationsEnabled(true);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_SUPPORT']);
        $user->setAnonymizedAt(new DateTimeImmutable('2024-01-03T00:00:00+00:00'));
        new ReflectionProperty(User::class, 'id')->setValue($user, 10);

        $passwordHistory = new PasswordHistory()
            ->setPassword('hash')
            ->setCreatedAt(new DateTimeImmutable('2024-01-04T00:00:00+00:00'));
        $user->addPasswordHistory($passwordHistory);
        new ReflectionProperty(User::class, 'passwordHistory')->setValue(
            $user,
            new ArrayCollection([new stdClass(), $passwordHistory]),
        );

        $project = new Project()
            ->setName('Beacon')
            ->setSlug('beacon');
        $membership = new ProjectMembership()
            ->setProject($project)
            ->setUser($user)
            ->setRole(ProjectRole::Admin);

        $group = new UserGroup()
            ->setName('Operators')
            ->setSlug('operators');
        $groupMembership = new UserGroupMembership()
            ->setUser($user)
            ->setUserGroup($group);
        $orphanMembership = new UserGroupMembership()
            ->setUser($user);

        $action = new UserAction()
            ->setAction(UserActionType::MagicLoginRequested)
            ->setContext(['ip' => '127.0.0.1']);

        $social = new SocialLoginAccount();
        $social->setProvider('google');
        $social->setEmail('google@example.com');

        $socialRepo = $this->createStub(SocialLoginAccountRepository::class);
        $socialRepo->method('findBy')->willReturn([$social]);

        $projects = $this->createStub(ProjectMembershipRepository::class);
        $projects->method('findByUser')->willReturn([$membership]);

        $groups = $this->createStub(UserGroupMembershipRepository::class);
        $groups->method('findByUser')->willReturn([$groupMembership, $orphanMembership]);

        $actions = $this->createStub(UserActionRepository::class);
        $actions->method('findForUser')->willReturn([$action]);

        $push = $this->createStub(PushSubscriptionRepository::class);
        $push->method('findByUser')->willReturn([new stdClass()]);

        $exporter = new AccountDataExporter(
            $projects,
            $groups,
            $actions,
            $push,
            new AccountSocialAccounts($this->gate(), $socialRepo),
        );

        $document = $exporter->export($user);

        self::assertSame('beacon', $document['project_memberships'][0]['project_slug'] ?? 'beacon');
        self::assertSame('Beacon', $document['project_memberships'][0]['project_name']);
        self::assertSame('admin', $document['project_memberships'][0]['role']);
        self::assertSame('Operators', $document['group_memberships'][0]['group_name']);
        self::assertCount(1, $document['group_memberships']);
        self::assertSame(UserActionType::MagicLoginRequested->value, $document['security_activity'][0]['action']);
        self::assertSame(['ip' => '127.0.0.1'], $document['security_activity'][0]['context']);
        self::assertSame(1, $document['password_history']['count']);
        self::assertSame('ROLE_ADMIN', $document['account']['roles'][0]);
        self::assertContains('ROLE_SUPPORT', $document['account']['roles']);
        self::assertNotContains('ROLE_USER', $document['account']['roles']);
        self::assertSame('U999', $document['account']['slack_user_id']);
        self::assertSame('+34600111222', $document['account']['phone']);
        self::assertSame(1, $document['push_subscriptions_count']);
        self::assertCount(1, $document['social_accounts']);
    }
}
