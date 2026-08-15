<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessGuard;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectMembershipResolver;
use App\Project\Service\ProjectShareGrantStore;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Builds {@see ProjectAccessService} with split collaborators for unit tests.
 */
final class ProjectAccessServiceFactory
{
    public static function create(
        ProjectMembershipRepository $membershipRepository,
        ProjectGroupAccessRepository $groupAccessRepository,
        ProjectShareLinkRepository $shareLinkRepository,
        AuthorizationCheckerInterface $authorizationChecker,
        RequestStack $requestStack,
    ): ProjectAccessService {
        $shareGrantStore = new ProjectShareGrantStore($requestStack, $shareLinkRepository);
        $membershipResolver = new ProjectMembershipResolver(
            $membershipRepository,
            $groupAccessRepository,
            $shareGrantStore,
            $authorizationChecker,
            $requestStack,
        );
        $accessGuard = new ProjectAccessGuard($membershipResolver, $shareGrantStore);

        return new ProjectAccessService($membershipResolver, $shareGrantStore, $accessGuard);
    }
}
