<?php

declare(strict_types=1);

namespace App\Shared\Menu;

/**
 * Marks administration sidebar links current for kit/admin areas (exact path match is too narrow).
 *
 * Preserves {@see \Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator} matches and
 * ORs kit route-prefix rules so child admin routes keep the parent item/branch highlighted.
 *
 * @phpstan-type MenuNode array{item: object, children: list<array<string, mixed>>, isCurrent?: bool, hasCurrentInBranch?: bool, href?: string}
 */
final class AdministrationMenuCurrentMarker
{
    /** @var array<string, list<string>> route name => prefixes that should light that sidebar item */
    private const array ROUTE_PREFIXES = [
        'admin_cookie_consent' => ['admin_cookie_consent', 'nowo_cookie_consent_'],
        'nowo_dashboard_menu_dashboard_index' => ['nowo_dashboard_menu_'],
        'nowo_breadcrumb_kit_dashboard_collections_index' => ['nowo_breadcrumb_kit_'],
        'nowo_routing_kit_panel' => ['nowo_routing_kit_'],
        'nowo_http_log_admin_index' => ['nowo_http_log_'],
        'nowo_site_backup_setup' => ['nowo_site_backup_'],
    ];

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<array<string, mixed>>
     */
    public function mark(array $tree, ?string $route): array
    {
        if (!\is_string($route) || '' === $route) {
            return $tree;
        }

        return array_map(
            fn (array $node): array => $this->markNode($node, $route),
            $tree,
        );
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function markNode(array $node, string $route): array
    {
        $children = $node['children'] ?? [];
        if (!\is_array($children)) {
            $children = [];
        }
        /** @var list<array<string, mixed>> $childList */
        $childList = array_values(array_filter($children, \is_array(...)));
        $children = array_map(
            fn (array $child): array => $this->markNode($child, $route),
            $childList,
        );

        $item = $node['item'] ?? null;
        $routeName = \is_object($item) && method_exists($item, 'getRouteName')
            ? $item->getRouteName()
            : null;
        // Keep URL/path matches from CurrentRouteTreeDecorator, then OR kit prefixes.
        $isCurrent = !empty($node['isCurrent']);
        if (\is_string($routeName) && isset(self::ROUTE_PREFIXES[$routeName])) {
            foreach (self::ROUTE_PREFIXES[$routeName] as $prefix) {
                if ($route === $prefix || str_starts_with($route, $prefix)) {
                    $isCurrent = true;
                    break;
                }
            }
        }

        $hasCurrentInBranch = $isCurrent || !empty($node['hasCurrentInBranch']);
        foreach ($children as $child) {
            if (!empty($child['hasCurrentInBranch']) || !empty($child['isCurrent'])) {
                $hasCurrentInBranch = true;
                break;
            }
        }

        $node['children'] = $children;
        $node['isCurrent'] = $isCurrent;
        $node['hasCurrentInBranch'] = $hasCurrentInBranch;

        return $node;
    }
}
