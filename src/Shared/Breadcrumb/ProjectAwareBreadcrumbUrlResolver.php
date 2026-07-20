<?php

declare(strict_types=1);

namespace App\Shared\Breadcrumb;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbUrlResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Fixes parent crumbs on nested project routes where `{id}` means the child entity
 * (issue, transaction) while ancestors still need the project id as `{id}`.
 *
 * Example: on `issue_show` (`/projects/{projectId}/issues/{id}`), crumbs for
 * `project_show` / `issue_index` must use `projectId`, not the issue `id`.
 */
#[AsDecorator(decorates: BreadcrumbUrlResolverInterface::class)]
final readonly class ProjectAwareBreadcrumbUrlResolver implements BreadcrumbUrlResolverInterface
{
    public function __construct(
        #[AutowireDecorated]
        private BreadcrumbUrlResolverInterface $inner,
        private RequestStack $requestStack,
        private RouterInterface $router,
    ) {
    }

    /**
     * @param array<string, scalar|null> $staticParams
     * @param list<string>|null          $dynamicKeys
     *
     * @return array{0: ?string, 1: array<string, scalar|null>}
     */
    public function resolve(
        string $routeName,
        array $staticParams,
        ?array $dynamicKeys,
    ): array {
        return $this->inner->resolve(
            $routeName,
            $this->mapProjectIdOntoId($routeName, $staticParams),
            $dynamicKeys,
        );
    }

    /**
     * @param array<string, scalar|null> $staticParams
     *
     * @return array<string, scalar|null>
     */
    private function mapProjectIdOntoId(string $routeName, array $staticParams): array
    {
        if (\array_key_exists('id', $staticParams)) {
            return $staticParams;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return $staticParams;
        }

        $route = $this->router->getRouteCollection()->get($routeName);
        if (null === $route) {
            return $staticParams;
        }

        $pathVars = $route->compile()->getPathVariables();
        $currentParams = (array) $request->attributes->get('_route_params', []);

        // Target route uses `{id}` as the project id (no `{projectId}` in the path).
        if (
            \in_array('id', $pathVars, true)
            && !\in_array('projectId', $pathVars, true)
            && \array_key_exists('projectId', $currentParams)
        ) {
            $staticParams['id'] = $currentParams['projectId'];
        }

        return $staticParams;
    }
}
