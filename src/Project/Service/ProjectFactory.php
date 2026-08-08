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

    /**
     * @param string|null $slug            Fixed slug when set (demo/dogfood); otherwise derived from $name
     * @param string|null $apiKeyPublicKey Deterministic public key (demo); otherwise generated
     * @param string|null $apiKeySecretKey Deterministic secret (demo); otherwise generated
     */
    public function create(
        User $owner,
        string $name,
        ?string $description = null,
        ?string $slug = null,
        string $apiKeyLabel = 'Default',
        ?string $apiKeyPublicKey = null,
        ?string $apiKeySecretKey = null,
    ): Project {
        $name = trim($name);
        if (null !== $slug && '' !== trim($slug)) {
            $slug = strtolower(trim($slug));
        } else {
            $slugger = new AsciiSlugger();
            $slug = strtolower($slugger->slug($name)->toString());
            if ('' === $slug) {
                $slug = 'project-'.bin2hex(random_bytes(3));
            }
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

        $project->addApiKey($this->apiKeyFactory->create(
            $project,
            $apiKeyLabel,
            $apiKeyPublicKey,
            $apiKeySecretKey,
        ));

        return $project;
    }
}
