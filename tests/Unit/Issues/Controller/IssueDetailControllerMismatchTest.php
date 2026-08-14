<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Controller\IssueDetailController;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Project\Entity\Project;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class IssueDetailControllerMismatchTest extends TestCase
{
    public function testShowRejectsIssueFromOtherProject(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->show($this->project(1), $this->issue($this->project(2)));
    }

    public function testEventShowRejectsEventWithoutMatchingIssueProject(): void
    {
        $event = new Event()->setIssue($this->issue($this->project(2)))->setEventId('evt-1');
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->eventShow($this->project(1), $event);
    }

    public function testEventShowRejectsEventWithoutIssue(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->eventShow($this->project(1), new Event()->setEventId('evt-orphan'));
    }

    #[DataProvider('mutationProvider')]
    public function testMutationsRejectIssueFromOtherProject(string $method): void
    {
        $request = Request::create('/x', Request::METHOD_POST);
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->{$method}($request, $this->project(1), $this->issue($this->project(2)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mutationProvider(): iterable
    {
        yield 'assign' => ['assign'];
        yield 'status' => ['status'];
        yield 'priority' => ['priority'];
        yield 'addComment' => ['addComment'];
        yield 'markDuplicate' => ['markDuplicate'];
    }

    private function controller(): IssueDetailController
    {
        $controller = new ReflectionClass(IssueDetailController::class)->newInstanceWithoutConstructor();
        $user = new User()->setEmail('triage@example.com');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        return $controller;
    }

    private function project(int $id): Project
    {
        $project = new Project()->setName('P'.$id)->setSlug('p'.$id);
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }

    private function issue(Project $project): Issue
    {
        return new Issue()
            ->setProject($project)
            ->setFingerprint('fp')
            ->setTitle('x')
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);
    }
}
