<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\User;
use App\Identity\Repository\InstancePermissionRepository;
use App\Identity\Repository\InstanceRoleRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants dotted permission keys (e.g. project.view) from assigned instance roles.
 *
 * ROLE_ADMIN always passes for catalogued (or custom) permission attributes.
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
