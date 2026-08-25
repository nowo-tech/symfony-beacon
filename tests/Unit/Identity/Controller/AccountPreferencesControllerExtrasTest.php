<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AccountPreferencesController;
use App\Identity\Entity\User;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Project\Repository\ProjectMembershipRepository;
use DateTime;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class AccountPreferencesControllerExtrasTest extends TestCase
{
    public function testPreferencesIndexRedirectsToProfile(): void
    {
        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/account/profile');
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $response = $controller->preferencesIndex();
        self::assertTrue($response->isRedirection());
        self::assertSame('/account/profile', $response->headers->get('Location'));
    }

    public function testRenderProfileIncludesAdminRoleAndPasswordExpiry(): void
    {
        $user = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $user->setPasswordChangedAt(new DateTime('-10 days'));

        $expiry = $this->createStub(PasswordExpiryServiceInterface::class);
        $expiry->method('isPasswordExpired')->willReturn(false);

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'passwordExpiryService')->setValue($controller, $expiry);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturnCallback(
            static function (string $template, array $context) use ($user): string {
                self::assertSame('account/profile.html.twig', $template);
                self::assertSame($user, $context['profile_user']);
                self::assertArrayHasKey('sensitive_form', $context);
                self::assertSame(['preferences.profile.role_admin'], $context['profile_roles']);
                self::assertGreaterThan(0, $context['password_expires_at']->getTimestamp());
                self::assertIsInt($context['password_days_remaining']);
                self::assertFalse($context['password_expired']);
                self::assertSame(90, $context['password_expiry_days']);

                return 'ok';
            },
        );
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        $form = $this->createStub(FormInterface::class);
        $sensitiveForm = $this->createStub(FormInterface::class);
        $method = new ReflectionMethod(AccountPreferencesController::class, 'renderProfile');
        $response = $method->invoke($controller, $form, $sensitiveForm, $user);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('ok', $response->getContent());
    }

    public function testProjectsAndGroupsRenderMembershipLists(): void
    {
        $user = new User()->setEmail('member@example.com');
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findByUser')->willReturn(['pm']);
        $groups = $this->createStub(UserGroupMembershipRepository::class);
        $groups->method('findByUser')->willReturn(['gm']);

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'projectMembershipRepository')->setValue($controller, $memberships);
        new ReflectionProperty(AccountPreferencesController::class, 'userGroupMembershipRepository')->setValue($controller, $groups);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $seen = [];
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::exactly(2))->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('ok', $controller->projects()->getContent());
        self::assertSame('ok', $controller->groups()->getContent());
        self::assertSame(['pm'], $seen['account/projects.html.twig']['project_memberships']);
        self::assertSame(['gm'], $seen['account/groups.html.twig']['group_memberships']);
    }
}
