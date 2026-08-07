<?php

declare(strict_types=1);

namespace App\Shared\Menu;

/**
 * Marks sidebar links current when the active route matches configured prefixes.
 *
 * @phpstan-type MenuNode array{item: object, children: list<array<string, mixed>>, isCurrent?: bool, hasCurrentInBranch?: bool, href?: string}
 */
abstract class RoutePrefixMenuCurrentMarker
{
    /** @return array<string, list<string>> route name => prefixes that should light that sidebar item */
    abstract protected function routePrefixes(): array;

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
        $isCurrent = !empty($node['isCurrent']);
        $prefixes = $this->routePrefixes();
        if (\is_string($routeName) && isset($prefixes[$routeName])) {
            foreach ($prefixes[$routeName] as $prefix) {
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
