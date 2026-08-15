<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminSocialLoginController;
use App\Identity\Entity\User;
use App\Identity\Service\SocialLoginCredentialSeeder;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Profile\ProfileRegistry;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class AdminSocialLoginNewRedirectTest extends TestCase
{
    public function testNewRedirectsToEditWhenBuiltinProviderExists(): void
    {
        $existing = new ReflectionClass(SocialLoginCredential::class)->newInstanceWithoutConstructor();
        $credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $credentials->method('findOneByProvider')->willReturn($existing);
        $credentials->method('findEnabledOrdered')->willReturn([]);

        $controller = new AdminSocialLoginController(
            $credentials,
            new SocialLoginCredentialSeeder($this->createStub(EntityManagerInterface::class), $credentials),
            $this->gate($credentials),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => '/'.$route.'/'.($params['provider'] ?? ''),
        );
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $response = $controller->new(Request::create('/admin/social-login/new?provider=google'));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin_social_login_edit/google', $response->getTargetUrl());
    }

    public function testNewIgnoresUnknownPresetProvider(): void
    {
        $credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $credentials->method('findOneByProvider')->willReturn(null);
        $credentials->method('findEnabledOrdered')->willReturn([]);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = new AdminSocialLoginController(
            $credentials,
            new SocialLoginCredentialSeeder($this->createStub(EntityManagerInterface::class), $credentials),
            $this->gate($credentials),
            new CsrfOnlyFormFactory($formFactory),
        );

        $seen = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );
        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('ok', $controller->new(Request::create('/new?provider=not-a-builtin'))->getContent());
        self::assertTrue($seen['admin/social_login/form.html.twig']['is_new']);
        self::assertSame('', $seen['admin/social_login/form.html.twig']['provider']);
    }

    private function gate(SocialLoginCredentialRepository $credentials): SocialLoginGate
    {
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
