<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Breadcrumb;

use App\Shared\Breadcrumb\ProjectAwareBreadcrumbUrlResolver;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class ProjectAwareBreadcrumbUrlResolverTest extends TestCase
{
    public function testPassesThroughWhenStaticIdAlreadyPresent(): void
    {
        $inner = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $inner->expects(self::once())
            ->method('resolve')
            ->with('project_show', ['id' => 42], null)
            ->willReturn(['/projects/42', ['id' => 42]]);

        $resolver = new ProjectAwareBreadcrumbUrlResolver(
            $inner,
            new RequestStack(),
            $this->createStub(RouterInterface::class),
        );

        self::assertSame(
            ['/projects/42', ['id' => 42]],
            $resolver->resolve('project_show', ['id' => 42], null),
        );
    }

    public function testMapsProjectIdOntoIdForProjectScopedAncestorRoutes(): void
    {
        $routes = new RouteCollection();
        $routes->add('project_show', new Route('/projects/{id}'));

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        $request = Request::create('/projects/5/issues/99');
        $request->attributes->set('_route_params', ['projectId' => 5, 'id' => 99]);
        $stack = new RequestStack();
        $stack->push($request);

        $inner = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $inner->expects(self::once())
            ->method('resolve')
            ->with('project_show', ['id' => 5], ['id'])
            ->willReturn(['/projects/5', ['id' => 5]]);

        $resolver = new ProjectAwareBreadcrumbUrlResolver($inner, $stack, $router);

        self::assertSame(
            ['/projects/5', ['id' => 5]],
            $resolver->resolve('project_show', [], ['id']),
        );
    }

    public function testLeavesParamsUntouchedWhenNoRequest(): void
    {
        $inner = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $inner->expects(self::once())
            ->method('resolve')
            ->with('project_show', [], null)
            ->willReturn([null, []]);

        $resolver = new ProjectAwareBreadcrumbUrlResolver(
            $inner,
            new RequestStack(),
            $this->createStub(RouterInterface::class),
        );

        self::assertSame([null, []], $resolver->resolve('project_show', [], null));
    }

    public function testLeavesParamsUntouchedWhenTargetRouteDoesNotExist(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $request = Request::create('/projects/5/issues/99');
        $request->attributes->set('_route_params', ['projectId' => 5, 'id' => 99]);
        $stack = new RequestStack();
        $stack->push($request);

        $inner = $this->createMock(BreadcrumbUrlResolverInterface::class);
        $inner->expects(self::once())
            ->method('resolve')
            ->with('missing_route', [], null)
            ->willReturn([null, []]);

        $resolver = new ProjectAwareBreadcrumbUrlResolver($inner, $stack, $router);

        self::assertSame([null, []], $resolver->resolve('missing_route', [], null));
    }
}
