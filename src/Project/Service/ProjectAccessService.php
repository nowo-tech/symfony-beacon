<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectMembershipRepository;
use App\Shared\ProjectRole;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ProjectAccessService
{
    public function __construct(
        private readonly ProjectMembershipRepository $membershipRepository,
    ) {
    }

    public function getMembership(Project $project, User $user): ?ProjectMembership
    {
        return $this->membershipRepository->findOneByProjectAndUser($project, $user);
    }

    public function requireMembership(Project $project, User $user): ProjectMembership
    {
        $membership = $this->getMembership($project, $user);
        if (null === $membership) {
            throw new AccessDeniedHttpException('You do not have access to this project.');
        }

        return $membership;
    }

    public function requireRole(Project $project, User $user, ProjectRole $minimum): ProjectMembership
    {
        $membership = $this->requireMembership($project, $user);
        $rank = [ProjectRole::Member->value => 1, ProjectRole::Admin->value => 2, ProjectRole::Owner->value => 3];
        if ($rank[$membership->getRole()->value] < $rank[$minimum->value]) {
            throw new AccessDeniedHttpException('Insufficient project permissions.');
        }

        return $membership;
    }
}
