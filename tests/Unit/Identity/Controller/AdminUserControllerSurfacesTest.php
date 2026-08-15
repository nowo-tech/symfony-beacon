<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminUserController;
use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountDataExporter;
use App\Identity\Service\AccountSocialAccounts;
use App\Identity\Service\AdminUserMutator;
use App\Identity\Service\UserActionRecorder;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Project\Repository\ProjectMembershipRepository;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Profile\ProfileRegistry;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AdminUserControllerSurfacesTest extends TestCase
{
    public function testToggleEnabledBlocksSelf(): void
    {
        $admin = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($admin, 1);
        new ReflectionProperty(User::class, 'uuid')->setValue($admin, '11111111-1111-7111-8111-111111111111');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $inner = $this->createStub(FormFactoryInterface::class);
        $inner->method('createNamed')->willReturn($form);
        $inner->method('create')->willReturn($form);

        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminUserController::class, 'csrfOnlyFormFactory')->setValue(
            $controller,
            new CsrfOnlyFormFactory($inner),
        );
        $em = $this->createStub(EntityManagerInterface::class);
        new ReflectionProperty(AdminUserController::class, 'adminUserMutator')->setValue(
            $controller,
            new AdminUserMutator(
                $this->createStub(UserRepository::class),
                new UserActionRecorder($em, new RequestStack()),
                $em,
                $this->createStub(UserPasswordHasherInterface::class),
            ),
        );
        $session = $this->boot($controller, $admin, flash: true);

        $response = $controller->toggleEnabled(Request::create('/toggle', Request::METHOD_POST), $admin);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/users', $response->getTargetUrl());
        self::assertSame(['flash.users.cannot_disable_self'], $session->getFlashBag()->peek('error'));
    }

    public function testExportDownloadsJsonForTargetUser(): void
    {
        $admin = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($admin, 1);
        $target = new User()->setEmail('member@example.com')->setDisplayName('Member');
        new ReflectionProperty(User::class, 'id')->setValue($target, 2);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $controller = new ReflectionClass(AdminUserController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminUserController::class, 'accountDataExporter')->setValue($controller, $this->exporter());
        new ReflectionProperty(AdminUserController::class, 'actionRecorder')->setValue(
            $controller,
            new UserActionRecorder($em, new RequestStack()),
        );
        $this->boot($controller, $admin);

        $response = $controller->export($target);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('member@example.com', $payload['account']['email']);
    }

    private function boot(object $controller, User $user, bool $flash = false): Session
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/users');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }

    private function exporter(): AccountDataExporter
    {
        $projects = $this->createStub(ProjectMembershipRepository::class);
        $projects->method('findByUser')->willReturn([]);
        $groups = $this->createStub(UserGroupMembershipRepository::class);
        $groups->method('findByUser')->willReturn([]);
        $actions = $this->createStub(UserActionRepository::class);
        $actions->method('findForUser')->willReturn([]);
        $push = $this->createStub(PushSubscriptionRepository::class);
        $push->method('findByUser')->willReturn([]);
        $socialRepo = $this->createStub(SocialLoginAccountRepository::class);
        $socialRepo->method('findBy')->willReturn([]);

        return new AccountDataExporter(
            $projects,
            $groups,
            $actions,
            $push,
            new AccountSocialAccounts($this->gate(), $socialRepo),
        );
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
                'social_login' => [
                    'mode' => 'disabled',
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
