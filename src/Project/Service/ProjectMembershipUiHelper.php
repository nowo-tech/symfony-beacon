<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectRole;

/**
 * Shared form choice builders for project membership and group-link UI.
 *
 * Callers should hydrate the project access graph ({@see ProjectRepository::hydrateAccessGraph})
 * before invoking methods that read memberships or group accesses.
 */
final class ProjectMembershipUiHelper
{
    /**
     * @param list<ProjectRole> $roles
     *
     * @return array<string, string>
     */
    public static function roleChoices(array $roles): array
    {
        $choices = [];
        foreach ($roles as $role) {
            $choices['project.members.role.'.$role->value] = $role->value;
        }

        return $choices;
    }

    /**
     * Groups not already linked to the project (preserves input order).
     *
     * @param iterable<UserGroup> $orderedGroups
     *
     * @return list<UserGroup>
     */
    public static function linkableGroups(Project $project, iterable $orderedGroups): array
    {
        $linkedIds = self::linkedGroupIds($project);
        $groups = [];
        foreach ($orderedGroups as $group) {
            $id = $group->getId();
            if (null === $id || isset($linkedIds[$id])) {
                continue;
            }
            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Choice map for linking a group: label => group UUID.
     * When {@see $groupMemberCounts} is non-empty, labels include member counts.
     *
     * @param iterable<UserGroup> $orderedGroups
     * @param array<int, int>     $groupMemberCounts
     *
     * @return array<string, string>
     */
    public static function groupChoicesForLinking(
        Project $project,
        iterable $orderedGroups,
        array $groupMemberCounts = [],
    ): array {
        $includeCounts = [] !== $groupMemberCounts;
        $choices = [];
        foreach (self::linkableGroups($project, $orderedGroups) as $group) {
            $groupId = $group->getId();
            if (null === $groupId) {
                continue;
            }

            $label = $includeCounts
                ? \sprintf('%s (%d)', $group->getName(), $groupMemberCounts[$groupId] ?? 0)
                : $group->getName();
            $choices[$label] = $group->getUuid();
        }

        return $choices;
    }

    /**
     * Direct members eligible to receive ownership (everyone except the actor).
     *
     * @return array<string, string>
     */
    public static function transferOwnershipChoices(Project $project, User $actor): array
    {
        $choices = [];
        foreach ($project->getMemberships() as $membership) {
            $member = $membership->getUser();
            if (null === $member || $member->getId() === $actor->getId()) {
                continue;
            }

            $choices[\sprintf(
                '%s (%s) - %s',
                $member->getDisplayName(),
                $member->getEmail(),
                $membership->getRole()->value,
            )] = $member->getUuid();
        }

        return $choices;
    }

    /**
     * @return array<int, true>
     */
    private static function linkedGroupIds(Project $project): array
    {
        $linkedIds = [];
        foreach ($project->getGroupAccesses() as $access) {
            $id = $access->getUserGroup()?->getId();
            if (null !== $id) {
                $linkedIds[$id] = true;
            }
        }

        return $linkedIds;
    }
}
