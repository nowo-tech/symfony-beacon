<?php

declare(strict_types=1);

namespace App\Shared\Form;

/**
 * Shared constants for dashboard GET filter forms (FormKit {@code filter} profile).
 *
 * Page / project / per_page fields are added via {@see AbstractGetFilterType}.
 */
final class DashboardProjectFilterFields
{
    /** @var list<int> */
    public const array PER_PAGE_SIZES = [10, 25, 50, 100];
}
