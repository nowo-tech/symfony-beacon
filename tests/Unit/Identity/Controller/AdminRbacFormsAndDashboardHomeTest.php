<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Controller\AdminInstancePermissionController;
use App\Identity\Controller\AdminInstanceRoleController;
use App\Identity\Controller\DashboardController;
use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\User;
use App\Identity\Service\ProductTourStepsBuilder;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\AccessibleProjectsProvider;
use App\Project\Service\ProjectAccessService;
use App\Tests\Support\ProjectAccessServiceFactory;
use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Form\GetFilterFormFactory;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class AdminRbacFormsAndDashboardHomeTest extends TestCase
{
    public function testBuildCreateAndEditRoleForms(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects(self::exactly(2))->method('create')->willReturn($form);

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        $container = new Container();
        $container->set('form.factory', $formFactory);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/roles');
        $container->set('router', $router);
        $controller->setContainer($container);

        $create = new ReflectionMethod(AdminInstanceRoleController::class, 'buildCreateForm');
        self::assertSame($form, $create->invoke($controller));

        $role = new InstanceRole();
        $role->setName('Support')->setCode('ROLE_SUPPORT');
        new ReflectionProperty(InstanceRole::class, 'id')->setValue($role, 5);
        new ReflectionProperty(InstanceRole::class, 'uuid')->setValue($role, '11111111-1111-7111-8111-111111111111');

        $edit = new ReflectionMethod(AdminInstanceRoleController::class, 'buildEditForm');
        self::assertSame($form, $edit->invoke($controller, $role, 'admin_roles_users'));
    }

    public function testBuildRoleUserRemoveFormsSkipsUsersWithoutId(): void
    {
        $withId = new User()->setEmail('a@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($withId, 8);
        $withoutId = new User()->setEmail('b@example.com');

        $role = new InstanceRole();
        $role->setName('Support')->setCode('ROLE_SUPPORT');
        new ReflectionProperty(InstanceRole::class, 'id')->setValue($role, 3);
        new ReflectionProperty(InstanceRole::class, 'uuid')->setValue($role, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $role->getUsers()->add($withId);
        $role->getUsers()->add($withoutId);

        $view = new FormView();
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($view);
        $inner = $this->createStub(FormFactoryInterface::class);
        $inner->method('createNamed')->willReturn($form);

        $controller = new ReflectionClass(AdminInstanceRoleController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminInstanceRoleController::class, 'csrfOnlyFormFactory')->setValue(
            $controller,
            new CsrfOnlyFormFactory($inner),
        );
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/remove');
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $method = new ReflectionMethod(AdminInstanceRoleController::class, 'buildRoleUserRemoveForms');
        $forms = $method->invoke($controller, $role);

        self::assertArrayHasKey(8, $forms);
        self::assertSame($view, $forms[8]);
        self::assertCount(1, $forms);
    }

    public function testPermissionBuildCreateAndEditForms(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects(self::exactly(2))->method('createNamed')->willReturn($form);

        $controller = new ReflectionClass(AdminInstancePermissionController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminInstancePermissionController::class, 'formFactory')->setValue($controller, $formFactory);

        $bag = new ParameterBag([
            'kernel.enabled_locales' => ['en'],
            'default_locale' => 'en',
        ]);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/permissions');
        $container = new Container();
        $container->set('parameter_bag', $bag);
        $container->set('router', $router);
        $controller->setContainer($container);

        $create = new ReflectionMethod(AdminInstancePermissionController::class, 'buildCreateForm');
        self::assertSame($form, $create->invoke($controller));

        $permission = new InstancePermission()->setKey('project.view')->setName('View');
        new ReflectionProperty(InstancePermission::class, 'id')->setValue($permission, 2);
        new ReflectionProperty(InstancePermission::class, 'uuid')->setValue($permission, 'bbbbbbbb-bbbb-7bbb-8bbb-bbbbbbbbbbbb');

        $edit = new ReflectionMethod(AdminInstancePermissionController::class, 'buildEditForm');
        self::assertSame($form, $edit->invoke($controller, $permission));
    }

    public function testDashboardHomeRendersProjectsAndSetupBannerForAdmin(): void
    {
        $user = new User()->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);

        $projectsRepo = $this->createStub(ProjectRepository::class);
        $projectsRepo->method('findAccessibleByUser')->willReturn([]);
        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findLastDaysForProjects')->willReturn([]);

        $settings = InstanceSettings::defaults();
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $tour = new ProductTourStepsBuilder(
            $this->createStub(TranslatorInterface::class),
            $security,
            ProjectAccessServiceFactory::create(
                $this->createStub(ProjectMembershipRepository::class),
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $this->createStub(AuthorizationCheckerInterface::class),
                new RequestStack(),
            ),
            $settingsRepo,
        );

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = new DashboardController(
            new AccessibleProjectsProvider($projectsRepo, new RequestStack()),
            $stats,
            $settingsRepo,
            $tour,
            new GetFilterFormFactory($formFactory),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturnCallback(
            static function (string $template, array $context): string {
                self::assertSame('dashboard/home.html.twig', $template);
                self::assertTrue($context['showSetupBanner']);
                self::assertSame([], $context['projects']);

                return 'home';
            },
        );

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/dashboard');

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.authorization_checker', $authChecker);
        $container->set('twig', $twig);
        $container->set('router', $router);
        $controller->setContainer($container);

        self::assertSame('home', $controller->home(Request::create('/dashboard'))->getContent());
    }
}
