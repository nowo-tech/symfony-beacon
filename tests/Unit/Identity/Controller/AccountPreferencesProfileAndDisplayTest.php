<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AccountPreferencesController;
use App\Identity\Entity\User;
use App\Identity\UserDisplayPreferenceDefaults;
use App\Notifications\Service\WebPushClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class AccountPreferencesProfileAndDisplayTest extends TestCase
{
    public function testProfileGetRendersForm(): void
    {
        $user = new User()->setEmail('me@example.com')->setRoles(['ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($user, 3);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'passwordExpiryService')->setValue(
            $controller,
            $this->createStub(PasswordExpiryServiceInterface::class),
        );

        $seen = [];
        $this->boot($controller, $user, $form, $seen);

        self::assertSame('ok', $controller->profile(Request::create('/account/profile'))->getContent());
        self::assertArrayHasKey('account/profile.html.twig', $seen);
        self::assertSame($user, $seen['account/profile.html.twig']['profile_user']);
    }

    public function testProfileRejectsEmailChangeWithoutCurrentPassword(): void
    {
        $user = new User()->setEmail('me@example.com')->setRoles(['ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($user, 3);

        $currentPassword = $this->createMock(FormInterface::class);
        $currentPassword->method('getData')->willReturn('');
        $currentPassword->expects(self::once())->method('addError')->with(self::isInstanceOf(FormError::class));

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnCallback(
            static function () use ($user, $form): Stub {
                $user->setEmail('new@example.com');

                return $form;
            },
        );
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->willReturnMap([
            ['currentPassword', $currentPassword],
        ]);
        $form->method('createView')->willReturn(new FormView());

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn(false);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('bad password');

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'passwordHasher')->setValue($controller, $hasher);
        new ReflectionProperty(AccountPreferencesController::class, 'translator')->setValue($controller, $translator);
        new ReflectionProperty(AccountPreferencesController::class, 'passwordExpiryService')->setValue(
            $controller,
            $this->createStub(PasswordExpiryServiceInterface::class),
        );

        $seen = [];
        $this->boot($controller, $user, $form, $seen);

        self::assertSame('ok', $controller->profile(Request::create('/account/profile', Request::METHOD_POST))->getContent());
        self::assertSame('me@example.com', $user->getEmail());
        self::assertArrayHasKey('account/profile.html.twig', $seen);
    }

    public function testDisplaySectionsRenderOnGet(): void
    {
        $user = new User()->setEmail('me@example.com')->setRoles(['ROLE_USER']);
        UserDisplayPreferenceDefaults::applyMissing($user, 'en');
        new ReflectionProperty(User::class, 'id')->setValue($user, 3);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccountPreferencesController::class, 'entityManager')->setValue(
            $controller,
            $this->createStub(EntityManagerInterface::class),
        );
        new ReflectionProperty(AccountPreferencesController::class, 'webPushFactory')->setValue(
            $controller,
            new WebPushClientFactory('', '', 'mailto:beacon@localhost'),
        );

        $seen = [];
        $this->boot($controller, $user, $form, $seen);

        self::assertSame('ok', $controller->display(Request::create('/account/display'))->getContent());
        self::assertSame('ok', $controller->displayPanels(Request::create('/account/display/panels'))->getContent());
        self::assertSame('ok', $controller->displayTours(Request::create('/account/display/tours'))->getContent());
        self::assertArrayHasKey('account/display.html.twig', $seen);
        self::assertArrayHasKey('account/display_panels.html.twig', $seen);
        self::assertArrayHasKey('account/display_tours.html.twig', $seen);
        self::assertArrayHasKey('replayForm', $seen['account/display_tours.html.twig']);
    }

    /**
     * @param array<string, array<string, mixed>> $seen
     */
    private function boot(
        object $controller,
        User $user,
        FormInterface $form,
        array &$seen,
    ): void {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/account');

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $container->set('router', $router);
        $container->set('parameter_bag', new ParameterBag([
            'default_locale' => 'en',
            'kernel.enabled_locales' => ['en'],
        ]));
        $controller->setContainer($container);
    }
}
