<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectRepository;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Export/import project metadata + memberships (089).
 *
 * Secrets (API keys) are never included. Admin import may create missing users by email;
 * panel import skips unknown emails and only updates the opened project when codes match.
 */
final readonly class ProjectConfigPortability
{
    public const string SCHEMA = 'beacon-project-bundle';
    public const int VERSION = 1;

    public function __construct(
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ProjectFactory $projectFactory,
    ) {
    }

    /**
     * @param list<Project> $projects
     *
     * @return array{
     *     schema: string,
     *     version: int,
     *     exported_at: string,
     *     projects: list<array<string, mixed>>
     * }
     */
    public function export(array $projects): array
    {
        $rows = [];
        foreach ($projects as $project) {
            $rows[] = $this->exportOne($project);
        }

        return [
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'exported_at' => new DateTimeImmutable()->format(\DATE_ATOM),
            'projects' => $rows,
        ];
    }

    /**
     * @return array{
     *     code: string,
     *     uuid: string,
     *     slug: string,
     *     name: string,
     *     description: ?string,
     *     ingest_enabled: bool,
     *     retention_days: ?int,
     *     retention_max_events: ?int,
     *     ingest_rate_limit_per_minute: ?int,
     *     event_quota_daily: ?int,
     *     event_quota_monthly: ?int,
     *     memberships: list<array{email: string, display_name: string, role: string, active: bool}>
     * }
     */
    public function exportOne(Project $project): array
    {
        $memberships = [];
        foreach ($project->getMemberships() as $membership) {
            $user = $membership->getUser();
            if (!$user instanceof User) {
                continue;
            }
            $memberships[] = [
                'email' => strtolower(trim($user->getEmail())),
                'display_name' => $user->getDisplayName(),
                'role' => $membership->getRole()->value,
                'active' => $membership->isActive(),
            ];
        }

        return [
            'code' => $project->getCode() !== '' ? $project->getCode() : $project->getSlug(),
            'uuid' => $project->getUuid(),
            'slug' => $project->getSlug(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'ingest_enabled' => $project->isIngestEnabled(),
            'retention_days' => $project->getRetentionDays(),
            'retention_max_events' => $project->getRetentionMaxEvents(),
            'ingest_rate_limit_per_minute' => $project->getIngestRateLimitPerMinute(),
            'event_quota_daily' => $project->getEventQuotaDaily(),
            'event_quota_monthly' => $project->getEventQuotaMonthly(),
            'memberships' => $memberships,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     projects_upserted: int,
     *     users_created: int,
     *     memberships_applied: int,
     *     memberships_skipped: list<string>,
     *     warnings: list<string>
     * }
     */
    public function importAdmin(array $payload, User $actor): array
    {
        $projects = $this->normalizeProjects($payload);
        $usersCreated = 0;
        $membershipsApplied = 0;
        $skipped = [];
        $warnings = [];
        $upserted = 0;

        foreach ($projects as $row) {
            $project = $this->upsertProject($row, $actor, createIfMissing: true);
            ++$upserted;
            $result = $this->applyMemberships($project, $row['memberships'], createMissingUsers: true, actor: $actor);
            $usersCreated += $result['users_created'];
            $membershipsApplied += $result['applied'];
            $skipped = [...$skipped, ...$result['skipped']];
            $warnings = [...$warnings, ...$result['warnings']];
        }

        return [
            'projects_upserted' => $upserted,
            'users_created' => $usersCreated,
            'memberships_applied' => $membershipsApplied,
            'memberships_skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    /**
     * Panel import: update only the opened project when codes match; never create users.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     projects_upserted: int,
     *     users_created: int,
     *     memberships_applied: int,
     *     memberships_skipped: list<string>,
     *     warnings: list<string>
     * }
     */
    public function importPanel(array $payload, Project $target, User $actor): array
    {
        $projects = $this->normalizeProjects($payload);
        if ([] === $projects) {
            throw new InvalidArgumentException('empty_projects');
        }

        $targetCode = $target->getCode() !== '' ? $target->getCode() : $target->getSlug();
        $match = null;
        foreach ($projects as $row) {
            if ($row['code'] === $targetCode || $row['uuid'] === $target->getUuid()) {
                $match = $row;
                break;
            }
        }
        if (null === $match) {
            if (1 === \count($projects)) {
                throw new InvalidArgumentException('code_mismatch');
            }
            throw new InvalidArgumentException('project_not_in_bundle');
        }

        $this->applyProjectFields($target, $match);
        $result = $this->applyMemberships($target, $match['memberships'], createMissingUsers: false, actor: $actor);

        return [
            'projects_upserted' => 1,
            'users_created' => 0,
            'memberships_applied' => $result['applied'],
            'memberships_skipped' => $result['skipped'],
            'warnings' => $result['warnings'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{
     *     code: string,
     *     uuid: string,
     *     slug: string,
     *     name: string,
     *     description: ?string,
     *     ingest_enabled: bool,
     *     retention_days: ?int,
     *     retention_max_events: ?int,
     *     ingest_rate_limit_per_minute: ?int,
     *     event_quota_daily: ?int,
     *     event_quota_monthly: ?int,
     *     memberships: list<array{email: string, display_name: string, role: string, active: bool}>
     * }>
     */
    private function normalizeProjects(array $payload): array
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA) {
            throw new InvalidArgumentException('invalid_schema');
        }
        $version = $payload['version'] ?? null;
        if (!\is_int($version) && !(\is_string($version) && ctype_digit($version))) {
            throw new InvalidArgumentException('invalid_version');
        }
        if ((int) $version !== self::VERSION) {
            throw new InvalidArgumentException('unsupported_version');
        }
        $list = $payload['projects'] ?? null;
        if (!\is_array($list)) {
            throw new InvalidArgumentException('invalid_projects');
        }

        $out = [];
        foreach ($list as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $code = strtolower(trim((string) ($item['code'] ?? '')));
            $slug = strtolower(trim((string) ($item['slug'] ?? $code)));
            $name = trim((string) ($item['name'] ?? ''));
            if ('' === $code || '' === $name) {
                throw new InvalidArgumentException('project_missing_code_or_name');
            }
            if ('' === $slug) {
                $slug = $code;
            }
            $memberships = [];
            $rawMembers = $item['memberships'] ?? [];
            if (\is_array($rawMembers)) {
                foreach ($rawMembers as $m) {
                    if (!\is_array($m)) {
                        continue;
                    }
                    $email = strtolower(trim((string) ($m['email'] ?? '')));
                    if ('' === $email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $role = strtolower(trim((string) ($m['role'] ?? 'member')));
                    if (null === ProjectRole::tryFrom($role)) {
                        $role = ProjectRole::Member->value;
                    }
                    $memberships[] = [
                        'email' => $email,
                        'display_name' => trim((string) ($m['display_name'] ?? $email)),
                        'role' => $role,
                        'active' => (bool) ($m['active'] ?? true),
                    ];
                }
            }

            $out[] = [
                'code' => $code,
                'uuid' => (string) ($item['uuid'] ?? ''),
                'slug' => $slug,
                'name' => $name,
                'description' => isset($item['description']) && \is_string($item['description']) && '' !== trim($item['description'])
                    ? trim($item['description']) : null,
                'ingest_enabled' => (bool) ($item['ingest_enabled'] ?? true),
                'retention_days' => $this->nullableInt($item['retention_days'] ?? null),
                'retention_max_events' => $this->nullableInt($item['retention_max_events'] ?? null),
                'ingest_rate_limit_per_minute' => $this->nullableInt($item['ingest_rate_limit_per_minute'] ?? null),
                'event_quota_daily' => $this->nullableInt($item['event_quota_daily'] ?? null),
                'event_quota_monthly' => $this->nullableInt($item['event_quota_monthly'] ?? null),
                'memberships' => $memberships,
            ];
        }

        return $out;
    }

    /**
     * @param array{
     *     code: string,
     *     uuid: string,
     *     slug: string,
     *     name: string,
     *     description: ?string,
     *     ingest_enabled: bool,
     *     retention_days: ?int,
     *     retention_max_events: ?int,
     *     ingest_rate_limit_per_minute: ?int,
     *     event_quota_daily: ?int,
     *     event_quota_monthly: ?int,
     *     memberships: list<array{email: string, display_name: string, role: string, active: bool}>
     * } $row
     */
    private function upsertProject(array $row, User $actor, bool $createIfMissing): Project
    {
        $existing = $this->projectRepository->findOneBy(['code' => $row['code']]);
        if ($existing instanceof Project) {
            $this->applyProjectFields($existing, $row);

            return $existing;
        }
        if (!$createIfMissing) {
            throw new InvalidArgumentException('project_not_found');
        }

        $slug = $row['slug'];
        if (null !== $this->projectRepository->findOneBy(['slug' => $slug])) {
            $slugger = new AsciiSlugger();
            $slug = strtolower($slugger->slug($row['name'].'-'.$row['code'])->toString());
            if ('' === $slug) {
                $slug = $row['code'].'-'.bin2hex(random_bytes(2));
            }
        }

        $project = $this->projectFactory->create($actor, $row['name'], $row['description'], $slug);
        $project->setCode($row['code']);
        $this->applyProjectFields($project, $row);
        $this->projectRepository->save($project);

        return $project;
    }

    /**
     * @param array{
     *     code: string,
     *     slug: string,
     *     name: string,
     *     description: ?string,
     *     ingest_enabled: bool,
     *     retention_days: ?int,
     *     retention_max_events: ?int,
     *     ingest_rate_limit_per_minute: ?int,
     *     event_quota_daily: ?int,
     *     event_quota_monthly: ?int
     * } $row
     */
    private function applyProjectFields(Project $project, array $row): void
    {
        $project->setName($row['name']);
        if ('' === $project->getCode()) {
            $project->setCode($row['code']);
        }
        $project->setDescription($row['description']);
        $project->setIngestEnabled($row['ingest_enabled']);
        $project->setRetentionDays($row['retention_days']);
        $project->setRetentionMaxEvents($row['retention_max_events']);
        $project->setIngestRateLimitPerMinute($row['ingest_rate_limit_per_minute']);
        $project->setEventQuotaDaily($row['event_quota_daily']);
        $project->setEventQuotaMonthly($row['event_quota_monthly']);
        $this->projectRepository->save($project);
    }

    /**
     * @param list<array{email: string, display_name: string, role: string, active: bool}> $memberships
     *
     * @return array{applied: int, users_created: int, skipped: list<string>, warnings: list<string>}
     */
    private function applyMemberships(Project $project, array $memberships, bool $createMissingUsers, User $actor): array
    {
        $applied = 0;
        $usersCreated = 0;
        $skipped = [];
        $warnings = [];

        foreach ($memberships as $row) {
            $user = $this->userRepository->findOneByEmail($row['email']);
            if (!$user instanceof User) {
                if (!$createMissingUsers) {
                    $skipped[] = $row['email'];
                    continue;
                }
                $user = $this->createDisabledUser($row['email'], $row['display_name']);
                ++$usersCreated;
            }

            $role = ProjectRole::from($row['role']);
            if (\in_array($role, [ProjectRole::Owner, ProjectRole::Full], true) && !$createMissingUsers) {
                // Panel cannot promote to owner/full via import — keep existing or demote to admin.
                $existing = null;
                foreach ($project->getMemberships() as $m) {
                    if ($m->getUser()?->getId() === $user->getId()) {
                        $existing = $m;
                        break;
                    }
                }
                if (!$existing instanceof ProjectMembership || !\in_array($existing->getRole(), [ProjectRole::Owner, ProjectRole::Full], true)) {
                    if (ProjectRole::Owner === $role || ProjectRole::Full === $role) {
                        $warnings[] = \sprintf('%s: role %s ignored on panel import (use Transfer / role UI)', $row['email'], $role->value);
                        $role = ProjectRole::Admin;
                    }
                }
            }

            $membership = null;
            foreach ($project->getMemberships() as $m) {
                if ($m->getUser()?->getId() === $user->getId()) {
                    $membership = $m;
                    break;
                }
            }
            if (!$membership instanceof ProjectMembership) {
                $membership = new ProjectMembership();
                $membership->setUser($user);
                $project->addMembership($membership);
            }
            $membership->setRole($role);
            $membership->setActive($row['active']);
            ++$applied;
        }

        $this->projectRepository->save($project);

        return [
            'applied' => $applied,
            'users_created' => $usersCreated,
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    private function createDisabledUser(string $email, string $displayName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('' !== $displayName ? $displayName : $email);
        $user->setRoles([]);
        $user->setEnabled(false);
        $plain = bin2hex(random_bytes(24));
        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        $user->setPasswordChangedAt(new DateTime());
        $this->userRepository->save($user);

        return $user;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (\is_int($value)) {
            return max(0, $value);
        }
        if (\is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
