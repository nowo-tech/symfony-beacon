<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\User;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use App\Project\Entity\Project;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants dotted permission keys (e.g. project.view) from assigned instance roles.
 *
 * ROLE_ADMIN always passes for catalogued (or custom) permission attributes when
 * there is no {@see Project} subject.
 *
 * When the subject is a {@see Project}, this voter abstains so product access is
 * decided only by {@see \App\Project\Security\ProjectPermissionVoter} (membership /
 * group role / ROLE_ADMIN via {@see \App\Project\Service\ProjectAccessService}).
 * Otherwise instance ROLE_PROJECT_* would GRANT under the default affirmative
 * strategy and bypass membership.
 *
 * @extends Voter<string, mixed|null>
 */
final class InstancePermissionVoter extends Voter
{
    public function __construct(
        private readonly InstancePermissionRepository $permissionRepository,
        private readonly InstanceRoleRepository $roleRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($subject instanceof Project) {
            return false;
        }

        return Permission::isDottedCapability($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $key = strtolower(trim($attribute));
        $known = $this->permissionRepository->findOneByKey($key);
        if (!$known instanceof InstancePermission) {
            // Unknown keys: deny (forces catalog registration before use).
            return false;
        }

        $userId = $user->getId();
        if (null === $userId) {
            return false;
        }

        return \in_array($key, $this->roleRepository->findPermissionKeysForUserId($userId), true);
    }
}
