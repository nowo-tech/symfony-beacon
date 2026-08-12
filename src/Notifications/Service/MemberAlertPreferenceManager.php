<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Identity\Entity\User;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\MemberProjectAlertPreference;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Load/save account and per-project member alert preferences (opt-out relational storage).
 */
final readonly class MemberAlertPreferenceManager
{
    public function __construct(
        private MemberProjectAlertPreferenceRepository $projectPreferenceRepository,
        private MemberAccountAlertEventRepository $accountEventRepository,
        private MemberProjectAlertEventRepository $projectEventRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $rawEvents
     */
    public function saveAccountEvents(User $user, bool $masterEnabled, array $rawEvents): void
    {
        $user->setMemberAlertsEnabled($masterEnabled);
        $existing = $this->accountEventRepository->findIndexedByEventForUser($user);

        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $key = $event->value;
            $raw = \is_array($rawEvents[$key] ?? null) ? $rawEvents[$key] : [];
            $enabled = \array_key_exists('enabled', $raw) ? (bool) $raw['enabled'] : true;
            $scope = MemberAlertScope::fromMixed($raw['scope'] ?? MemberAlertScope::All->value);
            $isDefault = $enabled && MemberAlertScope::All === $scope;
            $row = $existing[$key] ?? null;

            if ($isDefault) {
                if ($row instanceof MemberAccountAlertEvent) {
                    $this->entityManager->remove($row);
                }
                continue;
            }

            if (!$row instanceof MemberAccountAlertEvent) {
                $row = new MemberAccountAlertEvent();
                $row->setUser($user);
                $row->setEvent($event);
                $this->entityManager->persist($row);
            }
            $row->setEnabled($enabled);
            $row->setScope($scope);
        }
    }

    /**
     * @param list<array{
     *     project: Project,
     *     enabled: bool,
     *     resetOverrides?: bool,
     *     events?: array<string, mixed>
     * }> $projectRows
     */
    public function saveProjectPreferences(User $user, array $projectRows): void
    {
        foreach ($projectRows as $row) {
            $project = $row['project'];
            $enabled = $row['enabled'];
            $reset = (bool) ($row['resetOverrides'] ?? false);
            $events = \is_array($row['events'] ?? null) ? $row['events'] : [];

            $hasEventOverrides = false;
            if ($reset) {
                $this->projectEventRepository->deleteAllForUserAndProject($user, $project);
            } else {
                $hasEventOverrides = $this->syncProjectEventRows($user, $project, $events);
            }

            $pref = $this->projectPreferenceRepository->findOneByUserAndProject($user, $project);

            if ($enabled && !$hasEventOverrides) {
                if ($pref instanceof MemberProjectAlertPreference) {
                    $this->entityManager->remove($pref);
                }
                continue;
            }

            if (!$pref instanceof MemberProjectAlertPreference) {
                $pref = new MemberProjectAlertPreference();
                $pref->setUser($user);
                $pref->setProject($project);
                $this->entityManager->persist($pref);
            }
            $pref->setEnabled($enabled);
        }
    }

    /**
     * @param list<Project> $projects
     *
     * @return list<array{
     *     project: Project,
     *     enabled: bool,
     *     events: array<string, array{enabled: bool, scope: string}>,
     *     hasOverrides: bool
     * }>
     */
    public function projectRowsForUi(User $user, array $projects): array
    {
        $indexed = $this->projectPreferenceRepository->findIndexedByProjectIdForUser($user, $projects);
        $rows = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            $pref = null !== $id ? ($indexed[$id] ?? null) : null;
            $enabled = !$pref instanceof MemberProjectAlertPreference || $pref->isEnabled();
            $overrides = $this->projectEventRepository->findIndexedByEventForUserAndProject($user, $project);
            $account = $this->accountEventRepository->findIndexedByEventForUser($user);
            $events = [];
            foreach (MemberAlertEvent::casesInUiOrder() as $event) {
                [$evEnabled, $scope] = $this->mergeEvent($account, $overrides, $event);
                $events[$event->value] = [
                    'enabled' => $evEnabled,
                    'scope' => $scope->value,
                ];
            }
            $rows[] = [
                'project' => $project,
                'enabled' => $enabled,
                'events' => $events,
                'hasOverrides' => [] !== $overrides,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{enabled: bool, scope: string}>
     */
    public function accountEventsForUi(User $user): array
    {
        $account = $this->accountEventRepository->findIndexedByEventForUser($user);
        $out = [];
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            [$enabled, $scope] = $this->mergeEvent($account, [], $event);
            $out[$event->value] = [
                'enabled' => $enabled,
                'scope' => $scope->value,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $rawEvents
     */
    private function syncProjectEventRows(User $user, Project $project, array $rawEvents): bool
    {
        $account = $this->accountEventRepository->findIndexedByEventForUser($user);
        $existing = $this->projectEventRepository->findIndexedByEventForUserAndProject($user, $project);
        $hasOverrides = false;

        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $key = $event->value;
            $raw = \is_array($rawEvents[$key] ?? null) ? $rawEvents[$key] : [];
            $enabled = \array_key_exists('enabled', $raw) ? (bool) $raw['enabled'] : true;
            $scope = MemberAlertScope::fromMixed($raw['scope'] ?? MemberAlertScope::All->value);

            [$accountEnabled, $accountScope] = $this->mergeEvent($account, [], $event);
            $matchesAccount = $enabled === $accountEnabled && $scope === $accountScope;
            $row = $existing[$key] ?? null;

            if ($matchesAccount) {
                if ($row instanceof MemberProjectAlertEvent) {
                    $this->entityManager->remove($row);
                }
                continue;
            }

            if (!$row instanceof MemberProjectAlertEvent) {
                $row = new MemberProjectAlertEvent();
                $row->setUser($user);
                $row->setProject($project);
                $row->setEvent($event);
                $this->entityManager->persist($row);
            }
            $row->setEnabled($enabled);
            $row->setScope($scope);
            $hasOverrides = true;
        }

        return $hasOverrides;
    }

    /**
     * @param array<string, MemberAccountAlertEvent> $account
     * @param array<string, MemberProjectAlertEvent> $overrides
     *
     * @return array{0: bool, 1: MemberAlertScope}
     */
    private function mergeEvent(array $account, array $overrides, MemberAlertEvent $event): array
    {
        $key = $event->value;
        if (isset($overrides[$key]) && $overrides[$key] instanceof MemberProjectAlertEvent) {
            return [$overrides[$key]->isEnabled(), $overrides[$key]->getScope()];
        }
        if (isset($account[$key]) && $account[$key] instanceof MemberAccountAlertEvent) {
            return [$account[$key]->isEnabled(), $account[$key]->getScope()];
        }

        return [true, MemberAlertScope::All];
    }
}
