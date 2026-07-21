<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Shared\ProjectRole;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Create / revoke / resolve project share links (read-only viewer grants).
 */
final readonly class ProjectShareLinkManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectShareLinkRepository $shareLinkRepository,
        private ProjectAccessService $projectAccess,
        private UserActionRecorder $userActionRecorder,
    ) {
    }

    /**
     * @return array{link: ProjectShareLink, rawToken: string}
     */
    public function create(Project $project, User $actor, ?Issue $issue, DateTimeImmutable $expiresAt): array
    {
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);

        if ($issue instanceof Issue && $issue->getProject()?->getId() !== $project->getId()) {
            throw new InvalidArgumentException('issue_wrong_project');
        }

        if ($expiresAt <= new DateTimeImmutable()) {
            throw new InvalidArgumentException('expires_in_past');
        }

        $max = new DateTimeImmutable('+30 days');
        if ($expiresAt > $max) {
            throw new InvalidArgumentException('expires_too_far');
        }

        $raw = bin2hex(random_bytes(32));
        $link = new ProjectShareLink();
        $link->setProject($project);
        $link->setIssue($issue);
        $link->setCreatedBy($actor);
        $link->setTokenHash(hash('sha256', $raw));
        $link->setExpiresAt($expiresAt);
        $this->entityManager->persist($link);
        $this->userActionRecorder->record(
            UserActionType::ProjectShareLinkCreated,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'share_uuid' => $link->getUuid(),
                'issue_uuid' => $issue?->getUuid(),
                'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
            ],
        );
        $this->entityManager->flush();

        return ['link' => $link, 'rawToken' => $raw];
    }

    public function revoke(ProjectShareLink $link, User $actor): void
    {
        $project = $link->getProject();
        if (!$project instanceof Project) {
            throw new RuntimeException('missing_project');
        }
        $this->projectAccess->requireRole($project, $actor, ProjectRole::Admin);
        if ($link->isRevoked()) {
            return;
        }
        $link->revoke();
        $this->userActionRecorder->record(
            UserActionType::ProjectShareLinkRevoked,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'share_uuid' => $link->getUuid(),
            ],
        );
        $this->entityManager->flush();
    }

    public function findUsableByRawToken(string $rawToken): ?ProjectShareLink
    {
        $hash = hash('sha256', $rawToken);
        $link = $this->shareLinkRepository->findOneByTokenHash($hash);
        if (!$link instanceof ProjectShareLink || !$link->isUsable()) {
            return null;
        }

        return $link;
    }

    public function consume(ProjectShareLink $link, User $user): void
    {
        $project = $link->getProject();
        if (!$project instanceof Project) {
            throw new RuntimeException('missing_project');
        }

        $link->markUsed();
        $this->projectAccess->grantShareAccess(
            $project,
            $link->getIssue()?->getUuid(),
            $link->getExpiresAt()->getTimestamp(),
        );
        $this->userActionRecorder->record(
            UserActionType::ProjectShareLinkOpened,
            $user,
            $user,
            [
                'project_uuid' => $project->getUuid(),
                'share_uuid' => $link->getUuid(),
                'issue_uuid' => $link->getIssue()?->getUuid(),
            ],
        );
        $this->entityManager->flush();
    }
}
