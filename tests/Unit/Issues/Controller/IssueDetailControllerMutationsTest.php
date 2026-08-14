<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Controller\IssueDetailController;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueCommentCreator;
use App\Issues\Service\IssueDuplicateMarker;
use App\Issues\Service\IssueShowPageBuilder;
use App\Issues\Service\IssueStatusChanger;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class IssueDetailControllerMutationsTest extends TestCase
{
    public function testStatusInvalidFormFlashesError(): void
    {
        [$controller, $session, $project, $issue] = $this->triageController(
            $this->invalidForm(),
        );

        $response = $controller->status(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(['issues.status_invalid'], $session->getFlashBag()->peek('error'));
    }

    public function testStatusRejectsUnknownEnumValue(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['status' => 'not-a-status']);

        [$controller, $session, $project, $issue] = $this->triageController($form);

        $response = $controller->status(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(['issues.status_invalid'], $session->getFlashBag()->peek('error'));
    }

    public function testPriorityInvalidFormFlashesError(): void
    {
        [$controller, $session, $project, $issue] = $this->triageController(
            $this->invalidForm(),
        );

        $response = $controller->priority(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(['issues.priority_invalid'], $session->getFlashBag()->peek('error'));
    }

    public function testPrioritySavesWhenChanged(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['priority' => IssuePriority::High->value]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $recorder = new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack());

        [$controller, $session, $project, $issue] = $this->triageController($form, $em, $recorder);
        self::assertSame(IssuePriority::Medium, $issue->getPriority());

        $response = $controller->priority(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(IssuePriority::High, $issue->getPriority());
        self::assertSame(['issues.priority_saved'], $session->getFlashBag()->peek('success'));
    }

    public function testAssignInvalidFormFlashesError(): void
    {
        [$controller, $session, $project, $issue] = $this->triageController(
            $this->invalidForm(),
        );

        $response = $controller->assign(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(['issues.assignee_invalid'], $session->getFlashBag()->peek('error'));
    }

    public function testAddCommentInvalidFormFlashesError(): void
    {
        [$controller, $session, $project, $issue] = $this->triageController(
            $this->invalidForm(),
        );

        $response = $controller->addComment(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(['issues.comment_invalid'], $session->getFlashBag()->peek('error'));
    }

    public function testAddCommentMapsEmptyBodyFlash(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['body' => '   ']);

        // Empty body throws before deps are used; reflection instance is enough.
        [$controller, $session, $project, $issue] = $this->triageController(
            $form,
            commentCreator: new ReflectionClass(IssueCommentCreator::class)->newInstanceWithoutConstructor(),
        );

        $response = $controller->addComment(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(['issues.comment_empty'], $session->getFlashBag()->peek('error'));
    }

    public function testMarkDuplicateInvalidFormFlashesError(): void
    {
        [$controller, $session, $project, $issue] = $this->triageController(
            $this->invalidForm(),
        );

        $response = $controller->markDuplicate(Request::create('/x', Request::METHOD_POST), $project, $issue);
        self::assertSame('/issue', $response->getTargetUrl());
        self::assertSame(['issues.duplicate_invalid'], $session->getFlashBag()->peek('error'));
    }

    /**
     * @return array{0: IssueDetailController, 1: Session, 2: Project, 3: Issue}
     */
    private function triageController(
        FormInterface $form,
        ?EntityManagerInterface $em = null,
        ?UserActionRecorder $recorder = null,
        ?IssueCommentCreator $commentCreator = null,
    ): array {
        $user = new User()->setEmail('triage@example.com')->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);

        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $issue = new Issue()
            ->setProject($project)
            ->setFingerprint('fp')
            ->setTitle('Boom')
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);
        new ReflectionProperty(Issue::class, 'uuid')->setValue($issue, 'bbbbbbbb-bbbb-7bbb-8bbb-bbbbbbbbbbbb');

        $membership = new ProjectMembership()
            ->setProject($project)
            ->setUser($user)
            ->setRole(ProjectRole::Admin);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $access = new ProjectAccessService(
            $memberships,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        $controller = new IssueDetailController(
            $em ?? $this->createStub(EntityManagerInterface::class),
            new ReflectionClass(IssueAssigneeChanger::class)->newInstanceWithoutConstructor(),
            $commentCreator ?? new ReflectionClass(IssueCommentCreator::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(IssueDuplicateMarker::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(IssueShowPageBuilder::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(IssueStatusChanger::class)->newInstanceWithoutConstructor(),
            $access,
            $recorder ?? new ReflectionClass(UserActionRecorder::class)->newInstanceWithoutConstructor(),
        );

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/issue');

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('request_stack', $stack);
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        return [$controller, $session, $project, $issue];
    }

    private function invalidForm(): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        return $form;
    }
}
