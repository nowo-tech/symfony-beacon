<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use App\Project\Repository\ProjectShareLinkRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Session-scoped share-link grants (viewer access without membership).
 */
final readonly class ProjectShareGrantStore
{
    /** @var string Session map: project uuid => ['expires' => int, 'issue' => ?string, 'share' => ?string] */
    public const string SHARE_ACCESS_SESSION_KEY = ProjectAccessService::SHARE_ACCESS_SESSION_KEY;

    public function __construct(
        private RequestStack $requestStack,
        private ProjectShareLinkRepository $shareLinkRepository,
    ) {
    }

    /**
     * Grant temporary viewer access from a share link (session-scoped).
     */
    public function grantShareAccess(Project $project, ?string $issueUuid, int $expiresAtUnix, string $shareLinkUuid): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        /** @var array<string, array{expires: int, issue: ?string, share: ?string}> $grants */
        $grants = $session->get(self::SHARE_ACCESS_SESSION_KEY, []);
        $grants[$project->getUuid()] = [
            'expires' => $expiresAtUnix,
            'issue' => $issueUuid,
            'share' => $shareLinkUuid,
        ];
        $session->set(self::SHARE_ACCESS_SESSION_KEY, $grants);
    }

    public function hasActiveShareGrant(Project $project): bool
    {
        return null !== $this->getActiveShareEntry($project);
    }

    /** Project-wide share grant (no issue UUID restriction). */
    public function hasProjectWideShareGrant(Project $project): bool
    {
        $entry = $this->getActiveShareEntry($project);
        if (null === $entry) {
            return false;
        }

        $issue = $entry['issue'] ?? null;

        return null === $issue || '' === $issue;
    }

    /** Share grant covers this issue (project-wide or matching issue UUID). */
    public function hasShareGrantForIssue(Project $project, string $issueUuid): bool
    {
        $entry = $this->getActiveShareEntry($project);
        if (null === $entry) {
            return false;
        }

        $scoped = $entry['issue'] ?? null;
        if (null === $scoped || '' === $scoped) {
            return true;
        }

        return $scoped === $issueUuid;
    }

    /**
     * @return array{expires: int, issue: ?string, share: ?string}|null
     */
    public function getActiveShareEntry(Project $project): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        /** @var array<string, array{expires?: int, issue?: ?string, share?: ?string}> $grants */
        $grants = $request->getSession()->get(self::SHARE_ACCESS_SESSION_KEY, []);
        $entry = $grants[$project->getUuid()] ?? null;
        if (!\is_array($entry)) {
            return null;
        }

        $clearGrant = static function () use ($request, &$grants, $project): void {
            unset($grants[$project->getUuid()]);
            $request->getSession()->set(self::SHARE_ACCESS_SESSION_KEY, $grants);
        };

        $expires = (int) ($entry['expires'] ?? 0);
        if ($expires < time()) {
            $clearGrant();

            return null;
        }

        $shareUuid = isset($entry['share']) && \is_string($entry['share']) && '' !== $entry['share']
            ? $entry['share']
            : null;
        // Legacy grants without a share UUID cannot be re-validated after revoke.
        if (null === $shareUuid) {
            $clearGrant();

            return null;
        }

        $link = $this->shareLinkRepository->findOneByUuid($shareUuid);
        // Do not use isUsable(): max-uses exhaustion must block new opens, not revoke
        // an already-granted session. Revoke and expiry are the session invalidators.
        if (
            !$link instanceof ProjectShareLink
            || $link->getProject()?->getId() !== $project->getId()
            || $link->isRevoked()
            || $link->isExpired()
        ) {
            $clearGrant();

            return null;
        }

        return [
            'expires' => $expires,
            'issue' => isset($entry['issue']) && \is_string($entry['issue']) && '' !== $entry['issue']
                ? $entry['issue']
                : null,
            'share' => $shareUuid,
        ];
    }
}
