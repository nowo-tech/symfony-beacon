<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates ProjectApiKey rows with a unique publicKey.
 */
final readonly class ProjectApiKeyFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HumanFriendlyTokenGenerator $tokenGenerator,
    ) {
    }

    public function create(Project $project, string $label): ProjectApiKey
    {
        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $publicKey = $this->tokenGenerator->generateKey();
            if (null === $this->entityManager->getRepository(ProjectApiKey::class)->findOneBy(['publicKey' => $publicKey])) {
                return ProjectApiKey::generate($project, $label, $publicKey);
            }
        }

        return ProjectApiKey::generate($project, $label, $this->tokenGenerator->generateKey(4));
    }
}
