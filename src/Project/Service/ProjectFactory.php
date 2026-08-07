<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Creates a Project with Owner membership and a Default API key.
 */
final readonly class ProjectFactory
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectApiKeyFactory $apiKeyFactory,
    ) {
    }

    public function create(User $owner, string $name, ?string $description = null): Project
    {
        $name = trim($name);
        $slugger = new AsciiSlugger();
        $slug = strtolower($slugger->slug($name)->toString());
        if ('' === $slug) {
            $slug = 'project-'.bin2hex(random_bytes(3));
        }
        if (null !== $this->projectRepository->findOneBy(['slug' => $slug])) {
            $slug .= '-'.bin2hex(random_bytes(2));
        }

        $project = new Project();
        $project->setName($name);
        $project->setSlug($slug);
        $trimmed = null !== $description ? trim($description) : '';
        $project->setDescription('' !== $trimmed ? $trimmed : null);

        $membership = new ProjectMembership();
        $membership->setUser($owner);
        $membership->setRole(ProjectRole::Owner);
        $project->addMembership($membership);

        $project->addApiKey($this->apiKeyFactory->create($project, 'Default'));

        return $project;
    }
}
