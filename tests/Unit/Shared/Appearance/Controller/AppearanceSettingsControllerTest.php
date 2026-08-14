<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Appearance\Controller;

use App\Shared\Appearance\AppearanceSettingsSection;
use App\Shared\Appearance\AppearanceSettingsSubtab;
use App\Shared\Appearance\Controller\AppearanceSettingsController;
use App\Shared\Appearance\Entity\SiteAppearance;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class AppearanceSettingsControllerTest extends TestCase
{
    public function testIndexRedirectsToThemesSection(): void
    {
        $controller = $this->controller();
        $this->boot($controller, form: $this->form());

        $response = $controller->index();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/appearance/themes', $response->getTargetUrl());
    }

    public function testEditRejectsUnknownSection(): void
    {
        $controller = $this->controller();
        $this->boot($controller, form: $this->form());
        $this->expectException(NotFoundHttpException::class);
        $controller->edit(Request::create('/admin/appearance/nope'), 'nope');
    }

    public function testEditColorsWithoutSubRedirectsToDefaultSubtab(): void
    {
        $controller = $this->controller();
        $this->boot($controller, form: $this->form());

        $response = $controller->edit(Request::create('/admin/appearance/colors'), 'colors');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/appearance/colors/accents', $response->getTargetUrl());
    }

    public function testEditThemesGetRendersPicker(): void
    {
        $controller = $this->controller();
        $seen = [];
        $this->boot($controller, form: $this->form(), seen: $seen);

        self::assertSame('ok', $controller->edit(Request::create('/admin/appearance/themes'), 'themes')->getContent());
        $ctx = $seen['settings/appearance.html.twig'];
        self::assertSame(AppearanceSettingsSection::Themes, $ctx['section']);
        self::assertNull($ctx['form']);
        self::assertNotEmpty($ctx['lightThemes']);
        self::assertNotEmpty($ctx['darkThemes']);
    }

    public function testEditUnknownThemeFlashesError(): void
    {
        $themeForm = $this->createStub(FormInterface::class);
        $themeForm->method('handleRequest');
        $themeForm->method('isSubmitted')->willReturn(true);
        $themeForm->method('isValid')->willReturn(true);
        $themeForm->method('getData')->willReturn(['apply_theme' => 'not-a-real-theme']);
        $themeForm->method('createView')->willReturn(new FormView());

        $controller = $this->controller();
        $session = $this->boot($controller, form: $themeForm, flash: true);

        $response = $controller->edit(Request::create('/admin/appearance/themes', Request::METHOD_POST), 'themes');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['flash.appearance.theme_unknown'], $session->getFlashBag()->peek('error'));
    }

    public function testResolveSubtabRejectsInvalidColorsSub(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(AppearanceSettingsController::class, 'resolveSubtab');
        $this->expectException(NotFoundHttpException::class);
        $method->invoke($controller, AppearanceSettingsSection::Colors, 'not-a-sub');
    }

    public function testSectionParamsIncludeSubWhenPresent(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(AppearanceSettingsController::class, 'sectionParams');

        self::assertSame(
            ['section' => 'brand'],
            $method->invoke($controller, AppearanceSettingsSection::Brand, null),
        );
        self::assertSame(
            ['section' => 'colors', 'sub' => AppearanceSettingsSubtab::Status->value],
            $method->invoke($controller, AppearanceSettingsSection::Colors, AppearanceSettingsSubtab::Status),
        );
    }

    private function controller(): AppearanceSettingsController
    {
        $repo = $this->createStub(SiteAppearanceRepository::class);
        $repo->method('getOrCreate')->willReturn(SiteAppearance::defaults());

        return new AppearanceSettingsController($repo, new SiteAppearanceProvider($repo));
    }

    /** @return FormInterface<mixed> */
    private function form(): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    /**
     * @param FormInterface<mixed>                     $form
     * @param array<string, array<string, mixed>>|null $seen
     */
    private function boot(
        object $controller,
        FormInterface $form,
        ?array &$seen = null,
        bool $flash = false,
    ): Session {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $name, array $params = []): string {
                if ('admin_appearance_section' !== $name) {
                    return '/'.$name;
                }
                $path = '/admin/appearance/'.($params['section'] ?? 'themes');
                if (isset($params['sub'])) {
                    $path .= '/'.$params['sub'];
                }

                return $path;
            },
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                if (null !== $seen) {
                    $seen[$template] = $context;
                }

                return 'ok';
            },
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('twig', $twig);
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }
}
