<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Command\SeedDemoCommand;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectFactory;
use DateTime;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates the local demo admin + Symfony Beacon dogfood project (CLI and SiteBackup setup).
 *
 * Also grants every instance ROLE_ADMIN a direct membership on that project
 * so it appears in `/projects` (listing is membership-based; effective access for
 * admins already resolves as owner via ProjectAccessService).
 */
final readonly class DemoIdentitySeeder
{
    public function __construct(
        private UserRepository $userRepository,
        private ProjectRepository $projectRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ProjectFactory $projectFactory,
        private ProjectApiKeyFactory $apiKeyFactory,
    ) {
    }

    /**
     * @param bool $createDemoUser    when false (dogfood), never create admin@… and never prefer
     *                                `--email`; resolve ownership from existing ROLE_ADMIN accounts
     *                                (earliest registered first)
     * @param bool $useStableDemoKeys when true (local only), use documented DEMO_PUBLIC_KEY / DEMO_SECRET_KEY
     *
     * @return array{
     *     user_created: bool,
     *     project_created: bool,
     *     admins_granted: int,
     *     project: Project,
     *     api_key: ProjectApiKey,
     *     user: User
     * }
     *
     * @throws LogicException when $createDemoUser is false and no ROLE_ADMIN can own the dogfood project
     */
    public function seed(
        string $email = 'admin@symfony-beacon.local',
        string $password = 'admin123',
        bool $createDemoUser = true,
        bool $useStableDemoKeys = true,
    ): array {
        $userCreated = false;
        $user = null;

        if ($createDemoUser) {
            $user = $this->userRepository->findOneByEmail($email);

            if (!$user instanceof User) {
                $user = new User();
                $user->setEmail($email);
                $user->setDisplayName('Demo Admin');
                $user->setRoles(['ROLE_ADMIN']);
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                $user->setPasswordChangedAt(new DateTime());
                // Local QR approve/deny E2E (UC-AUTH-22): verified phone without SMS OTP yet.
                $user->setPhone('+34600000000');
                $user->setPhoneVerifiedAt(new DateTimeImmutable());
                $user->setPushNotificationsEnabled(true);
                $this->userRepository->save($user);
                $userCreated = true;
            } elseif ('admin@symfony-beacon.local' === $email) {
                // Re-seed keeps demo QR approver usable after profile clears verification.
                $dirty = false;
                if (null === $user->getPhone() || null === $user->getPhoneVerifiedAt()) {
                    $user->setPhone($user->getPhone() ?? '+34600000000');
                    $user->setPhoneVerifiedAt(new DateTimeImmutable());
                    $dirty = true;
                }
                if (!$user->isPushNotificationsEnabled()) {
                    $user->setPushNotificationsEnabled(true);
                    $dirty = true;
                }
                if ($dirty) {
                    $this->userRepository->save($user);
                }
            }
        } else {
            // Dogfood: ignore --email / leftover admin@…; use the first registered ROLE_ADMIN.
            $user = $this->userRepository->findFirstInstanceAdmin();
            if (!$user instanceof User) {
                throw new LogicException('Cannot dogfood without an existing ROLE_ADMIN. Register the first admin (or run app:seed-demo without --skip-demo-user), then re-run make dogfood.');
            }
        }

        $projectCreated = false;
        $project = $this->findDogfoodProject();
        $apiKey = null;
        if (!$project instanceof Project) {
            if (!$user instanceof User) {
                throw new LogicException('Cannot create the Symfony Beacon project without an existing user. Register an admin first, or run app:seed-demo without --skip-demo-user.');
            }

            $project = $this->createDogfoodProject($user, $useStableDemoKeys);
            $apiKey = $this->resolveDogfoodApiKey($project, $useStableDemoKeys);
            $projectCreated = true;
        } else {
            $this->syncDogfoodProjectIdentity($project);
            $apiKey = $this->resolveDogfoodApiKey($project, $useStableDemoKeys);
            if (!$apiKey instanceof ProjectApiKey) {
                $apiKey = $useStableDemoKeys
                    ? $this->apiKeyFactory->create(
                        $project,
                        SeedDemoCommand::DEMO_API_KEY_NAME,
                        SeedDemoCommand::DEMO_PUBLIC_KEY,
                        SeedDemoCommand::DEMO_SECRET_KEY,
                    )
                    : $this->apiKeyFactory->create($project, SeedDemoCommand::DEMO_API_KEY_NAME);
                $project->addApiKey($apiKey);
                $this->projectRepository->save($project);
                $projectCreated = true;
            }

            if (!$user instanceof User) {
                $ownerMembership = $project->getMemberships()->first();
                $user = $ownerMembership instanceof ProjectMembership
                    ? $ownerMembership->getUser()
                    : null;
            }
        }

        if (!$user instanceof User || !$apiKey instanceof ProjectApiKey) {
            throw new LogicException('Symfony Beacon project or owner could not be resolved.');
        }

        $adminsGranted = $this->grantDemoAccessToInstanceAdmins($project, $user);

        return [
            'user_created' => $userCreated,
            'project_created' => $projectCreated,
            'admins_granted' => $adminsGranted,
            'project' => $project,
            'api_key' => $apiKey,
            'user' => $user,
        ];
    }

    /**
     * Resolve the dogfood project by canonical or legacy slug.
     */
    public function findDogfoodProject(): ?Project
    {
        $project = $this->projectRepository->findOneBy(['slug' => SeedDemoCommand::DEMO_PROJECT_SLUG]);
        if ($project instanceof Project) {
            return $project;
        }

        $legacy = $this->projectRepository->findOneBy(['slug' => SeedDemoCommand::LEGACY_DEMO_PROJECT_SLUG]);

        return $legacy instanceof Project ? $legacy : null;
    }

    /**
     * Prefer the stable demo key (and re-activate it) when seeding locally; otherwise the first key.
     *
     * Operators may deactivate the demo key in Settings after creating another key — dogfood DSNs
     * still use {@see SeedDemoCommand::DEMO_PUBLIC_KEY} + {@see SeedDemoCommand::DEMO_SECRET_KEY}.
     */
    private function resolveDogfoodApiKey(Project $project, bool $useStableDemoKeys): ?ProjectApiKey
    {
        if ($useStableDemoKeys) {
            foreach ($project->getApiKeys() as $candidate) {
                if (!$candidate instanceof ProjectApiKey) {
                    continue;
                }
                if (SeedDemoCommand::DEMO_PUBLIC_KEY !== $candidate->getPublicKey()) {
                    continue;
                }
                if (!$candidate->isActive()) {
                    $candidate->setActive(true);
                    $this->projectRepository->save($project);
                }

                return $candidate;
            }
        }

        $first = $project->getApiKeys()->first();

        return $first instanceof ProjectApiKey ? $first : null;
    }

    /**
     * Keep name / slug / description aligned with current branding (upgrades legacy `demo`).
     */
    private function syncDogfoodProjectIdentity(Project $project): void
    {
        $changed = false;
        if (SeedDemoCommand::DEMO_PROJECT_NAME !== $project->getName()) {
            $project->setName(SeedDemoCommand::DEMO_PROJECT_NAME);
            $changed = true;
        }
        if (SeedDemoCommand::DEMO_PROJECT_SLUG !== $project->getSlug()) {
            $project->setSlug(SeedDemoCommand::DEMO_PROJECT_SLUG);
            $changed = true;
        }
        if (SeedDemoCommand::DEMO_PROJECT_DESCRIPTION !== $project->getDescription()) {
            $project->setDescription(SeedDemoCommand::DEMO_PROJECT_DESCRIPTION);
            $changed = true;
        }
        if ($changed) {
            $this->projectRepository->save($project);
        }
    }

    private function createDogfoodProject(User $owner, bool $useStableDemoKeys = true): Project
    {
        $project = $useStableDemoKeys
            ? $this->projectFactory->create(
                $owner,
                SeedDemoCommand::DEMO_PROJECT_NAME,
                SeedDemoCommand::DEMO_PROJECT_DESCRIPTION,
                SeedDemoCommand::DEMO_PROJECT_SLUG,
                SeedDemoCommand::DEMO_API_KEY_NAME,
                SeedDemoCommand::DEMO_PUBLIC_KEY,
                SeedDemoCommand::DEMO_SECRET_KEY,
            )
            : $this->projectFactory->create(
                $owner,
                SeedDemoCommand::DEMO_PROJECT_NAME,
                SeedDemoCommand::DEMO_PROJECT_DESCRIPTION,
                SeedDemoCommand::DEMO_PROJECT_SLUG,
                SeedDemoCommand::DEMO_API_KEY_NAME,
            );
        $this->projectRepository->save($project);

        return $project;
    }

    /**
     * Ensure every instance ROLE_ADMIN has a direct membership on the dogfood project.
     *
     * @return int Number of memberships created
     */
    private function grantDemoAccessToInstanceAdmins(Project $project, User $preferredOwner): int
    {
        $memberUserIds = [];
        foreach ($project->getMemberships() as $membership) {
            $id = $membership->getUser()->getId();
            if (null !== $id) {
                $memberUserIds[$id] = true;
            }
        }

        $added = 0;
        foreach ($this->userRepository->findInstanceAdmins() as $candidate) {
            $id = $candidate->getId();
            if (null === $id || isset($memberUserIds[$id])) {
                continue;
            }

            $membership = new ProjectMembership();
            $membership->setUser($candidate);
            $membership->setRole(
                $candidate->getId() === $preferredOwner->getId()
                    ? ProjectRole::Owner
                    : ProjectRole::Admin
            );
            $project->addMembership($membership);
            $memberUserIds[$id] = true;
            ++$added;
        }

        if ($added > 0) {
            $this->projectRepository->save($project);
        }

        return $added;
    }

    /**
     * Ensure the Symfony Beacon dogfood project exists for sample telemetry.
     *
     * Requires an owner (or any existing user). Does not create the fixed-password
     * local demo admin — use {@see seed()} from CLI only.
     *
     * @return array{user_created: bool, project_created: bool, project: Project}
     *
     * @throws LogicException when no owner can be resolved
     */
    public function ensureDemoProject(?User $owner = null): array
    {
        $existing = $this->findDogfoodProject();
        if ($existing instanceof Project) {
            $this->syncDogfoodProjectIdentity($existing);

            return [
                'user_created' => false,
                'project_created' => false,
                'project' => $existing,
            ];
        }

        if (!$owner instanceof User) {
            $owner = $this->userRepository->findOneBy([]);
        }

        if (!$owner instanceof User) {
            throw new LogicException('Cannot create the Symfony Beacon project without an existing user. Register an admin first or run app:seed-demo from the CLI.');
        }

        $project = $this->createDogfoodProject($owner);

        return [
            'user_created' => false,
            'project_created' => true,
            'project' => $project,
        ];
    }
}
