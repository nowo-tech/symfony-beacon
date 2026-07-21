<?php

declare(strict_types=1);

namespace App\Identity\Tour;

/**
 * Product-tour surfaces that can auto-start independently.
 */
enum ProductTourPage: string
{
    case Dashboard = 'dashboard';
    case ProjectIssues = 'project_issues';
    case Admin = 'admin';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
