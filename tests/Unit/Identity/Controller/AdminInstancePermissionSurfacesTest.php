<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AdminInstancePermissionController;
use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\User;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Service\UserActionRecorder;
use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Form\GetFilterFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class AdminInstancePermissionSurfacesTest extends TestCase
{
    public function testNewGetRedirectsWithNewFlag(): void
    {
        $controller = $this->controller();
        $this->boot($controller);

        $response = $controller->new(Request::create('/admin/permissions/new'));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/permissions?new=1', $response->getTargetUrl());
    }

    public function testEditGetRedirectsWithEditFlag(): void
    {
        $permission = new InstancePermission()->setKey('custom.view')->setName('Custom')->setSystem(false);
        new ReflectionProperty(InstancePermission::class, 'uuid')->setValue(
            $permission,
            '11111111-1111-7111-8111-111111111111',
        );

        $controller = $this->controller();
        $this->boot($controller);

        $response = $controller->edit(Request::create('/admin/permissions/x/edit'), $permission);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(
            '/admin/permissions?edit=11111111-1111-7111-8111-111111111111',
            $response->getTargetUrl(),
        );
    }

    public function testNewPostFlashesWhenKeyTaken(): void
    {
        $existing = new InstancePermission()->setKey('custom.view')->setName('Existing');
        $permissions = $this->createStub(InstancePermissionRepository::class);
        $permissions->method('findOneByKey')->willReturn($existing);
        $permissions->method('findAllOrdered')->willReturn([]);

        $incoming = new InstancePermission()->setKey('custom.view')->setName('Dup')->setCategory('custom');
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($incoming);
        $form->method('createView')->willReturn(new FormView());

        $controller = $this->controller($permissions, $form);
        $session = $this->boot($controller, flash: true);

        $response = $controller->new(Request::create('/admin/permissions/new', Request::METHOD_POST));
        self::assertSame('/admin/permissions?new=1', $response->getTargetUrl());
        self::assertSame(['flash.permissions.key_taken'], $session->getFlashBag()->peek('error'));
    }

    public function testIndexRendersEmptyCatalog(): void
    {
        $permissions = $this->createStub(InstancePermissionRepository::class);
        $permissions->method('findAllOrdered')->willReturn([]);

        $createForm = $this->createStub(FormInterface::class);
        $createForm->method('createView')->willReturn(new FormView());
        $searchForm = $this->createStub(FormInterface::class);
        $searchForm->method('createView')->willReturn(new FormView());

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($createForm);
        $formFactory->method('create')->willReturn($searchForm);

        $controller = new AdminInstancePermissionController(
            $permissions,
            new ReflectionClass(UserActionRecorder::class)->newInstanceWithoutConstructor(),
            $this->createStub(EntityManagerInterface::class),
            $formFactory,
            new CsrfOnlyFormFactory($formFactory),
            new GetFilterFormFactory($formFactory),
        );

        $seen = [];
        $this->boot($controller, flash: false, seen: $seen);

        self::assertSame('ok', $controller->index(Request::create('/admin/permissions'))->getContent());
        self::assertSame([], $seen['admin/permissions/index.html.twig']['permissions']);
        self::assertFalse($seen['admin/permissions/index.html.twig']['open_create']);
    }

    private function controller(
        ?InstancePermissionRepository $permissions = null,
        ?FormInterface $namedForm = null,
    ): AdminInstancePermissionController {
        $form = $namedForm ?? $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);
        $formFactory->method('create')->willReturn($form);

        $repo = $permissions ?? $this->createStub(InstancePermissionRepository::class);
        $repo->method('findAllOrdered')->willReturn([]);

        return new AdminInstancePermissionController(
            $repo,
            new ReflectionClass(UserActionRecorder::class)->newInstanceWithoutConstructor(),
            $this->createStub(EntityManagerInterface::class),
            $formFactory,
            new CsrfOnlyFormFactory($formFactory),
            new GetFilterFormFactory($formFactory),
        );
    }

    /**
     * @param array<string, mixed>|null $seen
     */
    private function boot(
        AdminInstancePermissionController $controller,
        bool $flash = false,
        ?array &$seen = null,
    ): Session {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $params = []): string {
                $query = [] === $params ? '' : '?'.http_build_query($params);

                return match ($route) {
                    'admin_permissions' => '/admin/permissions'.$query,
                    'admin_permissions_new' => '/admin/permissions/new',
                    'admin_permissions_edit' => '/admin/permissions/edit'.$query,
                    'admin_permissions_delete' => '/admin/permissions/delete'.$query,
                    default => '/'.$route.$query,
                };
            },
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $user = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $bag = new ParameterBag([
            'default_locale' => 'en',
            'kernel.enabled_locales' => ['en', 'es'],
        ]);

        $container = new Container();
        $container->set('router', $router);
        $container->set('security.token_storage', $tokenStorage);
        $container->set('parameter_bag', $bag);
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        if (null !== $seen) {
            $localSeen = &$seen;
            $twig = $this->createStub(Environment::class);
            $twig->method('render')->willReturnCallback(
                static function (string $name, array $context = []) use (&$localSeen): string {
                    $localSeen[$name] = $context;

                    return 'ok';
                },
            );
            $container->set('twig', $twig);
        }
        $controller->setContainer($container);

        return $session;
    }
}
