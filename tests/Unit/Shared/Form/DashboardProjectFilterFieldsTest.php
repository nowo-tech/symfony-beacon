<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Form;

use App\Shared\Form\DashboardProjectFilterFields;
use PHPUnit\Framework\TestCase;

final class DashboardProjectFilterFieldsTest extends TestCase
{
    public function testPerPageSizes(): void
    {
        self::assertContains(10, DashboardProjectFilterFields::PER_PAGE_SIZES);
        self::assertContains(100, DashboardProjectFilterFields::PER_PAGE_SIZES);
    }
}
