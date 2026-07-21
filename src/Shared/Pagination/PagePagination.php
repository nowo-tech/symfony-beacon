<?php

declare(strict_types=1);

namespace App\Shared\Pagination;

use Symfony\Component\HttpFoundation\Request;

/**
 * Server-side list pagination (query page / per_page). Never use DataTables paging.
 *
 * @phpstan-type PaginationArray array{page: int, per_page: int, total: int, total_pages: int, offset: int}
 */
final class PagePagination
{
    public const array ALLOWED_PER_PAGE = [10, 25, 50, 100];

    public const int DEFAULT_PER_PAGE = 25;

    /**
     * @return PaginationArray
     */
    public static function fromTotal(int $total, int $page, int $perPage): array
    {
        if (!\in_array($perPage, self::ALLOWED_PER_PAGE, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $total = max(0, $total);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'offset' => $offset,
        ];
    }

    /**
     * @return PaginationArray
     */
    public static function fromRequest(Request $request, int $total, int $defaultPerPage = self::DEFAULT_PER_PAGE): array
    {
        return self::fromTotal(
            $total,
            $request->query->getInt('page', 1),
            $request->query->getInt('per_page', $defaultPerPage),
        );
    }
}
