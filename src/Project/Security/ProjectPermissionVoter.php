<?php

declare(strict_types=1);

namespace App\Project\Security;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Enables declarative project permission checks with {@see ProjectPermission} constants.
 *
 * @extends Voter<string, Project>
 */
final class ProjectPermissionVoter extends Voter
{
    public function __construct(
        private readonly ProjectAccessService $projectAccess,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Project && ProjectPermission::isKnown($attribute);
    }

    /**
     * @param Project $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $access = $this->projectAccess->resolveAccess($subject, $user);

        return null !== $access && $access->grants($attribute);
    }
}
