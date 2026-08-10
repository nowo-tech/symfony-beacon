<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates ProjectApiKey rows with a unique high-entropy publicKey (or deterministic demo material).
 *
 * Human-friendly adjective-noun tokens are reserved for labels only — public keys use
 * {@see random_bytes()} so identifiers are not enumerable under rate limits.
 */
final readonly class ProjectApiKeyFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(
        Project $project,
        string $label,
        ?string $publicKey = null,
        ?string $secretKey = null,
    ): ProjectApiKey {
        if (null !== $publicKey && '' !== $publicKey) {
            return ProjectApiKey::generate($project, $label, $publicKey, $secretKey);
        }

        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $generatedPublicKey = bin2hex(random_bytes(16));
            if (null === $this->entityManager->getRepository(ProjectApiKey::class)->findOneBy(['publicKey' => $generatedPublicKey])) {
                return ProjectApiKey::generate($project, $label, $generatedPublicKey, $secretKey);
            }
        }

        return ProjectApiKey::generate($project, $label, bin2hex(random_bytes(24)), $secretKey);
    }
}
