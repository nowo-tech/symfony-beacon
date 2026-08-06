<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Menu;

use App\Shared\Menu\AdministrationMenuCurrentMarker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdministrationMenuCurrentMarkerTest extends TestCase
{
    #[Test]
    public function preservesExactCurrentAndExpandsBranchForKitPrefixRoutes(): void
    {
        $users = $this->node('admin_users', [], isCurrent: true);
        $httpLog = $this->node('nowo_http_log_admin_index', []);
        $observability = $this->node('section_obs', [$httpLog], itemType: 'section');
        $tree = [$this->node('section_access', [$users], itemType: 'section'), $observability];

        $marked = (new AdministrationMenuCurrentMarker())->mark($tree, 'nowo_http_log_admin_show');

        self::assertTrue($marked[0]['children'][0]['isCurrent']);
        self::assertTrue($marked[0]['children'][0]['hasCurrentInBranch']);
        self::assertTrue($marked[1]['hasCurrentInBranch']);
        self::assertTrue($marked[1]['children'][0]['isCurrent']);
        self::assertTrue($marked[1]['children'][0]['hasCurrentInBranch']);
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array{item: object, children: list<array<string, mixed>>, isCurrent: bool, hasCurrentInBranch: bool}
     */
    private function node(
        string $routeName,
        array $children,
        bool $isCurrent = false,
        string $itemType = 'link',
    ): array {
        $item = new class($routeName, $itemType) {
            public function __construct(
                private readonly string $routeName,
                private readonly string $itemType,
            ) {
            }

            public function getRouteName(): string
            {
                return $this->routeName;
            }

            public function getItemType(): string
            {
                return $this->itemType;
            }
        };

        return [
            'item' => $item,
            'children' => $children,
            'isCurrent' => $isCurrent,
            'hasCurrentInBranch' => $isCurrent,
        ];
    }
}
