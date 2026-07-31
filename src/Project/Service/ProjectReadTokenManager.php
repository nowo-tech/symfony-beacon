<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectReadToken;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Create / revoke project read API tokens (SHA-256 at rest).
 */
final readonly class ProjectReadTokenManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectReadTokenRepository $tokenRepository,
        private ProjectAccessService $projectAccess,
        private UserActionRecorder $userActionRecorder,
    ) {
    }

    /**
     * @return array{token: ProjectReadToken, rawToken: string}
     */
    public function create(Project $project, User $actor, string $label): array
    {
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);
        $label = trim($label);
        if ('' === $label) {
            $label = 'Read token';
        }

        $raw = 'brt_'.bin2hex(random_bytes(24));
        $prefix = substr($raw, 0, 12);
        $token = new ProjectReadToken();
        $token->setProject($project);
        $token->setCreatedBy($actor);
        $token->setLabel(mb_substr($label, 0, 120));
        $token->setPrefix($prefix);
        $token->setTokenHash(hash('sha256', $raw));
        $this->entityManager->persist($token);
        $this->userActionRecorder->record(
            UserActionType::ProjectApiKeyCreated,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'read_token_uuid' => $token->getUuid(),
                'read_token_prefix' => $prefix,
                'kind' => 'read_api',
            ],
        );
        $this->entityManager->flush();

        return ['token' => $token, 'rawToken' => $raw];
    }

    public function revoke(ProjectReadToken $token, User $actor): void
    {
        $project = $token->getProject();
        if (!$project instanceof Project) {
            throw new RuntimeException('missing_project');
        }
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);
        if (!$token->isActive()) {
            return;
        }
        $token->revoke();
        $this->userActionRecorder->record(
            UserActionType::ProjectApiKeyRevoked,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'read_token_uuid' => $token->getUuid(),
                'kind' => 'read_api',
            ],
        );
        $this->entityManager->flush();
    }

    public function authenticate(string $rawToken): ?ProjectReadToken
    {
        $rawToken = trim($rawToken);
        if ('' === $rawToken || !str_starts_with($rawToken, 'brt_')) {
            return null;
        }

        $token = $this->tokenRepository->findActiveByTokenHash(hash('sha256', $rawToken));
        if (!$token instanceof ProjectReadToken) {
            return null;
        }
        $token->markUsed();
        $this->entityManager->flush();

        return $token;
    }
}
