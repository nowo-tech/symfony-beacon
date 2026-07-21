<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Pagination\PagePagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PagePaginationTest extends TestCase
{
    public function testFromTotalClampsPageAndNormalizesPerPage(): void
    {
        $pagination = PagePagination::fromTotal(55, 99, 7);

        self::assertSame(25, $pagination['per_page']);
        self::assertSame(3, $pagination['total_pages']);
        self::assertSame(3, $pagination['page']);
        self::assertSame(50, $pagination['offset']);
        self::assertSame(55, $pagination['total']);
    }

    public function testFromRequestReadsQueryParams(): void
    {
        $request = Request::create('/list', Request::METHOD_GET, ['page' => '2', 'per_page' => '10']);
        $pagination = PagePagination::fromRequest($request, 25);

        self::assertSame(2, $pagination['page']);
        self::assertSame(10, $pagination['per_page']);
        self::assertSame(10, $pagination['offset']);
        self::assertSame(3, $pagination['total_pages']);
    }

    public function testEmptyTotalStillHasOnePage(): void
    {
        $pagination = PagePagination::fromTotal(0, 1, 25);

        self::assertSame(0, $pagination['total']);
        self::assertSame(1, $pagination['total_pages']);
        self::assertSame(1, $pagination['page']);
        self::assertSame(0, $pagination['offset']);
    }
}
