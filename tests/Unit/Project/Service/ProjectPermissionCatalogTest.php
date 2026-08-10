<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Service\InstancePermissionCategoryCatalog;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectPermissionCatalog;
use PHPUnit\Framework\TestCase;

final class ProjectPermissionCatalogTest extends TestCase
{
    public function testDefinitionsCoverAllProjectPermissionKeys(): void
    {
        $definitions = ProjectPermissionCatalog::definitions();
        self::assertCount(8, $definitions);

        $keys = array_column($definitions, 'key');
        self::assertSame(ProjectPermission::allValues(), $keys);

        foreach ($definitions as $definition) {
            self::assertTrue(
                InstancePermissionCategoryCatalog::isKnown($definition['category']),
                'Unknown category: '.$definition['category'],
            );
        }
    }
}
