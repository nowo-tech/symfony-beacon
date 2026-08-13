<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Entity\Project;

/**
 * Resolves an optional project UUID against a list of already-authorized projects.
 */
final class AccessibleProjectFilter
{
    /**
     * @param list<Project> $accessible
     */
    public static function resolve(array $accessible, string $uuid): ?Project
    {
        if ('' === $uuid) {
            return null;
        }

        foreach ($accessible as $project) {
            if ($project->getUuid() === $uuid) {
                return $project;
            }
        }

        return null;
    }

    /**
     * Choice map for dashboard project filters: project name => UUID.
     *
     * @param list<Project> $accessible
     *
     * @return array<string, string>
     */
    public static function choiceMap(array $accessible): array
    {
        $projectChoices = [];
        foreach ($accessible as $project) {
            $projectChoices[$project->getName()] = $project->getUuid();
        }

        return $projectChoices;
    }
}
