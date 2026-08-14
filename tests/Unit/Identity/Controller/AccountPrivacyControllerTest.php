<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AccountPrivacyController;
use App\Identity\Entity\User;
use App\Identity\Exception\AccountAnonymizeException;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountAnonymizer;
use App\Identity\Service\AccountDataExporter;
use App\Identity\Service\AccountSocialAccounts;
use App\Identity\Service\UserActionRecorder;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Project\Repository\ProjectMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Profile\ProfileRegistry;
use Nowo\AuthKitBundle\Repository\SocialLoginAccountRepository;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class AccountPrivacyControllerTest extends TestCase
{
    public function testExportDownloadsJsonAndRecordsAction(): void
    {
        $user = new User()->setEmail('privacy@example.com')->setDisplayName('Privacy');
        new ReflectionProperty(User::class, 'id')->setValue($user, 4);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $controller = new AccountPrivacyController(
            $this->exporter(),
            $this->anonymizer(),
            new UserActionRecorder($em, new RequestStack()),
            new TokenStorage(),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $this->createStub(UrlGeneratorInterface::class));
        $controller->setContainer($container);

        $response = $controller->export();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('beacon-account-'.$user->getUuid().'.json', (string) $response->headers->get('Content-Disposition'));
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('beacon-account-export/v1', $payload['schema']);
        self::assertSame('privacy@example.com', $payload['account']['email']);
    }

    public function testPrivacyPageRendersWhenUserCanAnonymize(): void
    {
        $user = new User()->setEmail('privacy@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 4);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $seen = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );

        $controller = new AccountPrivacyController(
            $this->exporter(),
            $this->anonymizer(),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
            new TokenStorage(),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/account/privacy/anonymize');
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $container->set('router', $router);
        $controller->setContainer($container);

        self::assertSame('ok', $controller->privacy()->getContent());
        self::assertTrue($seen['account/privacy.html.twig']['can_anonymize']);
        self::assertSame([], $seen['account/privacy.html.twig']['sole_owner_projects']);
        self::assertFalse($seen['account/privacy.html.twig']['is_last_admin']);
    }

    public function testFlashForAnonymizeExceptionMapsReasonCodes(): void
    {
        $controller = new AccountPrivacyController(
            $this->exporter(),
            $this->anonymizer(),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
            $this->createStub(TokenStorageInterface::class),
        );
        $method = new ReflectionMethod(AccountPrivacyController::class, 'flashForAnonymizeException');

        self::assertSame(
            'flash.privacy.already_anonymized',
            $method->invoke($controller, new AccountAnonymizeException(AccountAnonymizeException::ALREADY_ANONYMIZED)),
        );
        self::assertSame(
            'flash.privacy.sole_owner',
            $method->invoke($controller, new AccountAnonymizeException(AccountAnonymizeException::SOLE_OWNER)),
        );
        self::assertSame(
            'flash.privacy.last_admin',
            $method->invoke($controller, new AccountAnonymizeException(AccountAnonymizeException::LAST_ADMIN)),
        );
        self::assertSame(
            'flash.privacy.anonymize_failed',
            $method->invoke($controller, new AccountAnonymizeException('other')),
        );
    }

    private function anonymizer(): AccountAnonymizer
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $users = $this->createStub(UserRepository::class);
        $users->method('countAdmins')->willReturn(2);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findByUser')->willReturn([]);
        $push = $this->createStub(PushSubscriptionRepository::class);
        $push->method('findByUser')->willReturn([]);
        $social = $this->createStub(SocialLoginAccountRepository::class);
        $social->method('findBy')->willReturn([]);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);

        return new AccountAnonymizer(
            $em,
            $users,
            $memberships,
            $push,
            $social,
            $hasher,
            new UserActionRecorder($em, new RequestStack()),
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
}
