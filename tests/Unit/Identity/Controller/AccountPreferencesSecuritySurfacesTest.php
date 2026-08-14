<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\AccountSecurityActivity;
use App\Identity\Controller\AccountPreferencesController;
use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Service\AccountSocialAccounts;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Profile\ProfileRegistry;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class AccountPreferencesSecuritySurfacesTest extends TestCase
{
    public function testSecurityHistoryAndActivityRender(): void
    {
        $user = new User()->setEmail('sec@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 4);

        $actions = $this->createStub(UserActionRepository::class);
        $actions->method('findForUser')->willReturn(['timeline']);

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'userActionRepository')->setValue($controller, $actions);

        $seen = [];
        $this->boot($controller, $user, $seen);

        self::assertSame('ok', $controller->securityHistory()->getContent());
        self::assertSame('ok', $controller->securityActivity()->getContent());
        self::assertArrayHasKey('account/security_history.html.twig', $seen);
        self::assertSame(['timeline'], $seen['account/security_activity.html.twig']['security_actions']);
        self::assertSame(AccountSecurityActivity::TIMELINE_LIMIT, 50);
    }

    public function testRenderSecurityIncludesSocialFlags(): void
    {
        $user = new User()->setEmail('sec@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 4);

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'accountSocialAccounts')->setValue(
            $controller,
            new AccountSocialAccounts($this->gate('disabled'), $this->createStub(SocialLoginAccountRepository::class)),
        );

        $seen = [];
        $this->boot($controller, $user, $seen);

        $form = $this->createStub(FormInterface::class);
        $method = new ReflectionMethod(AccountPreferencesController::class, 'renderSecurity');
        self::assertSame('ok', $method->invoke($controller, $form)->getContent());
        self::assertFalse($seen['account/security.html.twig']['social_login_enabled']);
        self::assertSame([], $seen['account/security.html.twig']['linked_social_accounts']);
    }

    public function testDisplayNotificationsRendersMemberAlertPayload(): void
    {
        $user = new User()->setEmail('alerts@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 6);
        $user->setMemberAlertsEnabled(true);

        $accountEvents = $this->createStub(MemberAccountAlertEventRepository::class);
        $accountEvents->method('findIndexedByEventForUser')->willReturn([]);
        $projectPrefs = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $projectPrefs->method('findIndexedByProjectIdForUser')->willReturn([]);
        $projectEvents = $this->createStub(MemberProjectAlertEventRepository::class);
        $projectEvents->method('findIndexedByProjectIdForUser')->willReturn([]);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([]);

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'webPushFactory')->setValue(
            $controller,
            new WebPushClientFactory('', '', 'mailto:beacon@localhost'),
        );
        new ReflectionProperty(AccountPreferencesController::class, 'accessibleProjects')->setValue(
            $controller,
            new AccessibleProjectsProvider($projects, new RequestStack()),
        );
        new ReflectionProperty(AccountPreferencesController::class, 'memberAlertPreferenceManager')->setValue(
            $controller,
            new MemberAlertPreferenceManager(
                $projectPrefs,
                $accountEvents,
                $projectEvents,
                $this->createStub(EntityManagerInterface::class),
            ),
        );
        new ReflectionProperty(AccountPreferencesController::class, 'entityManager')->setValue(
            $controller,
            $this->createStub(EntityManagerInterface::class),
        );

        $seen = [];
        $this->boot($controller, $user, $seen);

        self::assertSame('ok', $controller->displayNotifications()->getContent());
        $ctx = $seen['account/display_notifications.html.twig'];
        self::assertFalse($ctx['push_available']);
        self::assertTrue($ctx['member_alert_initial']['memberAlertsEnabled']);
        self::assertSame([], $ctx['member_alert_projects']);
        self::assertNotEmpty($ctx['member_alert_initial']['events']);
    }

    public function testSecurityGetRendersForm(): void
    {
        $user = new User()->setEmail('sec@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 4);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'accountSocialAccounts')->setValue(
            $controller,
            new AccountSocialAccounts($this->gate('disabled'), $this->createStub(SocialLoginAccountRepository::class)),
        );

        $seen = [];
        $this->boot($controller, $user, $seen, $form);

        self::assertSame('ok', $controller->security(Request::create('/account/security'))->getContent());
        self::assertArrayHasKey('account/security.html.twig', $seen);
        self::assertFalse($seen['account/security.html.twig']['social_login_enabled']);
    }

    /**
     * @param array<string, array<string, mixed>> $seen
     */
    private function boot(object $controller, User $user, array &$seen, ?FormInterface $form = null): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $container->set('parameter_bag', new ParameterBag(['default_locale' => 'en']));
        if ($form instanceof FormInterface) {
            $formFactory = $this->createStub(FormFactoryInterface::class);
            $formFactory->method('create')->willReturn($form);
            $container->set('form.factory', $formFactory);
        }
        $controller->setContainer($container);
    }

    private function gate(string $mode): SocialLoginGate
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
                'social_login' => [
                    'mode' => $mode,
                    'create_user_if_missing' => true,
                    'require_verified_email' => true,
                ],
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
            ],
        ], 'main'), $credentials);
    }
}
