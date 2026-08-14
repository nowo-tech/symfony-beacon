<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminSocialLoginController;
use App\Identity\Entity\User;
use App\Identity\Service\SocialLoginCredentialSeeder;
use App\Shared\Form\CsrfOnlyFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Profile\ProfileRegistry;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Nowo\AuthKitBundle\SocialLogin\SocialLoginGate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

final class AdminSocialLoginControllerSurfacesTest extends TestCase
{
    public function testIndexRendersMissingBuiltinsWhenEmpty(): void
    {
        $credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $credentials->method('findBy')->willReturn([]);

        $controller = new AdminSocialLoginController(
            $credentials,
            new SocialLoginCredentialSeeder($this->createStub(EntityManagerInterface::class), $credentials),
            $this->gate(),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );

        $seen = [];
        $this->boot($controller, $seen);

        self::assertSame('ok', $controller->index()->getContent());
        $ctx = $seen['admin/social_login/index.html.twig'];
        self::assertSame([], $ctx['credentials']);
        self::assertSame(SocialLoginCredentialSeeder::BUILTIN_PROVIDERS, $ctx['missing_builtins']);
        self::assertFalse($ctx['social_login_active']);
        self::assertSame([], $ctx['deleteForms']);
    }

    public function testDelete404WhenCredentialMissing(): void
    {
        $credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $credentials->method('findOneByProvider')->willReturn(null);

        $controller = new AdminSocialLoginController(
            $credentials,
            new SocialLoginCredentialSeeder($this->createStub(EntityManagerInterface::class), $credentials),
            $this->gate(),
            new CsrfOnlyFormFactory($this->createStub(FormFactoryInterface::class)),
        );
        $seen = [];
        $this->boot($controller, $seen);

        $this->expectException(NotFoundHttpException::class);
        $controller->delete(Request::create('/delete', Request::METHOD_POST), 'google');
    }

    public function testDeleteRejectsInvalidCsrf(): void
    {
        $credential = new ReflectionClass(SocialLoginCredential::class)
            ->newInstanceWithoutConstructor();

        $credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $credentials->method('findOneByProvider')->willReturn($credential);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);
        $inner = $this->createStub(FormFactoryInterface::class);
        $inner->method('createNamed')->willReturn($form);
        $inner->method('create')->willReturn($form);

        $controller = new AdminSocialLoginController(
            $credentials,
            new SocialLoginCredentialSeeder($this->createStub(EntityManagerInterface::class), $credentials),
            $this->gate(),
            new CsrfOnlyFormFactory($inner),
        );
        $seen = [];
        $this->boot($controller, $seen);

        $this->expectException(AccessDeniedException::class);
        $controller->delete(Request::create('/delete', Request::METHOD_POST), 'google');
    }

    public function testHelpersStillCoveredViaReflection(): void
    {
        $controller = new ReflectionClass(AdminSocialLoginController::class)->newInstanceWithoutConstructor();
        $nullable = new ReflectionMethod(AdminSocialLoginController::class, 'nullableUrl');
        self::assertNull($nullable->invoke($controller, '  '));
    }

    /**
     * @param array<string, array<string, mixed>> $seen
     */
    private function boot(object $controller, array &$seen): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/social-login');
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );
        $container = new Container();
        $container->set('router', $router);
        $container->set('twig', $twig);
        $controller->setContainer($container);
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
