<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueMentionRepository;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\MemberProjectAlertPreference;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Project\Entity\Project;

/**
 * Opt-out gates for member Mercure / Web Push alerts.
 */
final readonly class MemberAlertPreferenceEvaluator
{
    public function __construct(
        private MemberProjectAlertPreferenceRepository $projectPreferenceRepository,
        private MemberAccountAlertEventRepository $accountEventRepository,
        private MemberProjectAlertEventRepository $projectEventRepository,
        private IssueMentionRepository $mentionRepository,
    ) {
    }

    public function shouldNotify(User $user, Project $project, Issue $issue, MemberAlertEvent $event): bool
    {
        if (!$user->isMemberAlertsEnabled()) {
            return false;
        }

        $projectPref = $this->projectPreferenceRepository->findOneByUserAndProject($user, $project);
        if ($projectPref instanceof MemberProjectAlertPreference && !$projectPref->isEnabled()) {
            return false;
        }

        [$enabled, $scope] = $this->resolveEventSettings($user, $project, $event);
        if (!$enabled) {
            return false;
        }

        if (MemberAlertScope::All === $scope) {
            return true;
        }

        return $this->isInvolved($user, $issue);
    }

    public function isInvolved(User $user, Issue $issue): bool
    {
        $assignee = $issue->getAssignee();
        $userId = $user->getId();
        if ($assignee instanceof User && null !== $userId && $assignee->getId() === $userId) {
            return true;
        }

        return $this->mentionRepository->isUserMentionedOnIssue($user, $issue);
    }

    /**
     * @return array{0: bool, 1: MemberAlertScope}
     */
    public function resolveEventSettings(User $user, Project $project, MemberAlertEvent $event): array
    {
        $projectRow = $this->projectEventRepository->findOneByUserProjectAndEvent($user, $project, $event);
        if ($projectRow instanceof MemberProjectAlertEvent) {
            return [$projectRow->isEnabled(), $projectRow->getScope()];
        }

        $accountRow = $this->accountEventRepository->findOneByUserAndEvent($user, $event);
        if ($accountRow instanceof MemberAccountAlertEvent) {
            return [$accountRow->isEnabled(), $accountRow->getScope()];
        }

        return [true, MemberAlertScope::All];
    }
}
