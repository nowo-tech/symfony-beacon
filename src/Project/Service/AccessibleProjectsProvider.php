<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Request-scoped cache for {@see ProjectRepository::findAccessibleByUser()}.
 *
 * Dashboard filters and account surfaces call the heavy DISTINCT query repeatedly
 * in a single request; memoize on the current Request attributes.
 */
final readonly class AccessibleProjectsProvider
{
    private const string ATTR_PREFIX = '_beacon_accessible_projects_';

    public function __construct(
        private ProjectRepository $projectRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<Project>
     */
    public function forUser(User $user, ?string $query = null): array
    {
        $userId = $user->getId();
        $normalizedQuery = null !== $query && '' !== trim($query) ? trim($query) : null;
        $request = $this->requestStack->getCurrentRequest();

        if (null === $userId || null === $request) {
            return $this->projectRepository->findAccessibleByUser($user, $normalizedQuery);
        }

        $cacheKey = self::ATTR_PREFIX.$userId.'_'.hash('xxh128', $normalizedQuery ?? '');
        if ($request->attributes->has($cacheKey)) {
            /** @var list<Project> $cached */
            $cached = $request->attributes->get($cacheKey);

            return $cached;
        }

        $projects = $this->projectRepository->findAccessibleByUser($user, $normalizedQuery);
        $request->attributes->set($cacheKey, $projects);

        return $projects;
    }
}
