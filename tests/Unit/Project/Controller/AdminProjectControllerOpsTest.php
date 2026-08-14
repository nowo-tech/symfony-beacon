<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Project\Controller\AdminProjectController;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AdminProjectShowPageBuilder;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectFactory;
use App\Project\Service\ProjectHistoryClearer;
use App\Project\Service\ProjectOpsStatsService;
use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Form\GetFilterFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
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

final class AdminProjectControllerOpsTest extends TestCase
{
    public function testToggleIngestSuspendsAndResumes(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 4);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, '11111111-1111-7111-8111-111111111111');
        $project->setIngestEnabled(true);

        $actor = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $form = $this->validForm(['enabled' => '']);
        $controller = $this->controller($em, $form);
        $session = $this->boot($controller, $actor, showUrl: '/admin/projects/11111111-1111-7111-8111-111111111111');

        $response = $controller->toggleIngest($project, Request::create('/x', Request::METHOD_POST));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertFalse($project->isIngestEnabled());
        self::assertSame(['flash.admin_projects.ingest_suspended'], $session->getFlashBag()->peek('success'));
    }

    public function testEnableAndDisableViewAsMember(): void
    {
        $actor = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist');
        $em->expects(self::exactly(2))->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('createView')->willReturn(new FormView());
        $form->method('getData')->willReturnOnConsecutiveCalls(
            ['project_uuid' => '', 'redirect' => '/dashboard'],
            ['redirect' => '/admin/projects'],
        );

        $controller = $this->controller($em, $form);
        $session = $this->boot($controller, $actor, showUrl: '/admin/projects');

        $request = Request::create('/admin/view-as-member/enable', Request::METHOD_POST);
        $request->setSession($session);
        $enable = $controller->enableViewAsMember($request);
        self::assertTrue($session->get(ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY));
        self::assertSame('/dashboard', $enable->getTargetUrl());

        $disableRequest = Request::create('/admin/view-as-member/disable', Request::METHOD_POST);
        $disableRequest->setSession($session);
        $disable = $controller->disableViewAsMember($disableRequest);
        self::assertFalse($session->has(ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY));
        self::assertSame('/admin/projects', $disable->getTargetUrl());
    }

    /** @param FormInterface<mixed> $form */
    private function controller(EntityManagerInterface $em, FormInterface $form): AdminProjectController
    {
        return new AdminProjectController(
            new UserActionRecorder($em, new RequestStack()),
            $em,
            new ReflectionClass(ProjectHistoryClearer::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectOpsStatsService::class)->newInstanceWithoutConstructor(),
            $this->createStub(ProjectRepository::class),
            new ReflectionClass(ProjectFactory::class)->newInstanceWithoutConstructor(),
            $this->csrfFactory($form),
            new GetFilterFormFactory($this->createStub(FormFactoryInterface::class)),
            new ReflectionClass(AdminProjectShowPageBuilder::class)->newInstanceWithoutConstructor(),
        );
    }

    /** @param FormInterface<mixed> $form */
    private function csrfFactory(FormInterface $form): CsrfOnlyFormFactory
    {
        $factory = $this->createStub(FormFactoryInterface::class);
        $factory->method('createNamed')->willReturn($form);
        $factory->method('create')->willReturn($form);

        return new CsrfOnlyFormFactory($factory);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return FormInterface<mixed>
     */
    private function validForm(array $data): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    private function boot(AdminProjectController $controller, User $actor, string $showUrl): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string => match ($route) {
                'admin_projects_show' => $showUrl,
                'admin_projects' => '/admin/projects',
                'admin_projects_ingest_toggle', 'admin_view_as_member_enable', 'admin_view_as_member_disable' => '/action',
                default => '/'.$route,
            },
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($actor, 'main', $actor->getRoles()));

        $container = new Container();
        $container->set('router', $router);
        $container->set('request_stack', $stack);
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        return $session;
    }
}
