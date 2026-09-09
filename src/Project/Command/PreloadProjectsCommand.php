<?php

declare(strict_types=1);

namespace App\Project\Command;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectConfigPortability;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Production-safe preload of projects (+ optional ingest API keys) from operator-owned JSON.
 *
 * Intended for Ansible/devops: store bundles outside the Beacon git tree (e.g. vaulted
 * under devops/ansible/.secrets/beacon-preload/), copy onto the VPS, then run this command.
 *
 * Does not use DEMO_* keys. Does not replace app:seed-platform / site setup admin creation.
 */
#[AsCommand(
    name: 'app:preload-projects',
    description: 'Import beacon-project-bundle JSON (+ optional API keys file) — operator preload, not committed demo data',
)]
final class PreloadProjectsCommand extends Command
{
    public const string API_KEYS_SCHEMA = 'beacon-api-keys';

    public function __construct(
        private readonly ProjectConfigPortability $portability,
        private readonly UserRepository $userRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectApiKeyFactory $apiKeyFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('bundle', null, InputOption::VALUE_REQUIRED, 'Path to beacon-project-bundle JSON')
            ->addOption('api-keys', null, InputOption::VALUE_REQUIRED, 'Optional path to beacon-api-keys JSON (secrets; never commit)')
            ->addOption('actor-email', null, InputOption::VALUE_REQUIRED, 'ROLE_ADMIN email used as import actor (default: first instance admin)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate bundle (+ optional api-keys) JSON only; do not write');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bundlePath = (string) $input->getOption('bundle');
        if ('' === $bundlePath) {
            $io->error('Missing --bundle=/path/to/projects.json');

            return Command::FAILURE;
        }

        try {
            $payload = $this->readJsonObject($bundlePath);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (($payload['schema'] ?? null) !== ProjectConfigPortability::SCHEMA) {
            $io->error(\sprintf('Expected schema "%s", got "%s".', ProjectConfigPortability::SCHEMA, (string) ($payload['schema'] ?? '')));

            return Command::FAILURE;
        }

        $actor = $this->resolveActor((string) ($input->getOption('actor-email') ?? ''));
        if (!$actor instanceof User) {
            $io->error('No ROLE_ADMIN actor found. Finish instance setup (admin user) before preload.');

            return Command::FAILURE;
        }

        $apiKeysPath = (string) ($input->getOption('api-keys') ?? '');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $projectCount = $this->portability->countValidatedProjects($payload);
            $keyCount = 0;
            if ('' !== $apiKeysPath) {
                $keyCount = $this->validateApiKeysFile($apiKeysPath);
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->success(\sprintf(
                'Dry-run OK: bundle valid (%d project row(s))%s, actor=%s',
                $projectCount,
                '' !== $apiKeysPath ? \sprintf(', api-keys valid (%d key row(s))', $keyCount) : '',
                $actor->getEmail(),
            ));

            return Command::SUCCESS;
        }

        try {
            /** @var array{projects: array{projects_upserted: int, users_created: int, memberships_applied: int, memberships_skipped: list<string>, warnings: list<string>}, keys: array{created: int, skipped: int}|null} $outcome */
            $outcome = $this->entityManager->wrapInTransaction(function () use ($payload, $actor, $apiKeysPath, $io): array {
                $result = $this->portability->importAdmin($payload, $actor);
                $keysResult = null;
                if ('' !== $apiKeysPath) {
                    $keysResult = $this->applyApiKeys($apiKeysPath, $io);
                }

                return ['projects' => $result, 'keys' => $keysResult];
            });
        } catch (InvalidArgumentException|RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (Throwable $e) {
            $io->error('Preload failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $result = $outcome['projects'];
        $io->success(\sprintf(
            'Projects upserted=%d users_created=%d memberships_applied=%d',
            $result['projects_upserted'],
            $result['users_created'],
            $result['memberships_applied'],
        ));
        if ([] !== $result['memberships_skipped']) {
            $io->note('Memberships skipped: '.implode(', ', $result['memberships_skipped']));
        }
        foreach ($result['warnings'] as $warning) {
            $io->warning($warning);
        }

        if (null !== $outcome['keys']) {
            $io->success(\sprintf(
                'API keys created=%d skipped=%d',
                $outcome['keys']['created'],
                $outcome['keys']['skipped'],
            ));
        }

        return Command::SUCCESS;
    }

    private function resolveActor(string $email): ?User
    {
        if ('' !== trim($email)) {
            $user = $this->userRepository->findOneByEmail($email);
            if ($user instanceof User && \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }

            return null;
        }

        return $this->userRepository->findFirstInstanceAdmin();
    }

    /**
     * Validate api-keys JSON shape without writing. Returns key row count.
     */
    private function validateApiKeysFile(string $path): int
    {
        $payload = $this->readJsonObject($path);
        if (($payload['schema'] ?? null) !== self::API_KEYS_SCHEMA) {
            throw new RuntimeException(\sprintf('Expected schema "%s" in %s', self::API_KEYS_SCHEMA, $path));
        }
        $rows = $payload['keys'] ?? null;
        if (!\is_array($rows)) {
            throw new RuntimeException('api-keys file must contain a "keys" array');
        }
        foreach ($rows as $i => $row) {
            if (!\is_array($row)) {
                throw new RuntimeException(\sprintf('keys[%d] must be an object', $i));
            }
            $code = strtolower(trim((string) ($row['project_code'] ?? '')));
            $publicKey = trim((string) ($row['public_key'] ?? ''));
            $secretKey = trim((string) ($row['secret_key'] ?? ''));
            if ('' === $code || '' === $publicKey || '' === $secretKey) {
                throw new RuntimeException(\sprintf('keys[%d] requires project_code, public_key, secret_key', $i));
            }
        }

        return \count($rows);
    }

    /**
     * @return array{created: int, skipped: int}
     */
    private function applyApiKeys(string $path, SymfonyStyle $io): array
    {
        $payload = $this->readJsonObject($path);
        // Shape already validated in dry-run / pre-flight; re-check for safety.
        $this->validateApiKeysFile($path);
        $rows = $payload['keys'];
        \assert(\is_array($rows));

        $created = 0;
        $skipped = 0;
        foreach ($rows as $i => $row) {
            \assert(\is_array($row));
            $code = strtolower(trim((string) ($row['project_code'] ?? '')));
            $label = trim((string) ($row['label'] ?? 'preload'));
            $publicKey = trim((string) ($row['public_key'] ?? ''));
            $secretKey = trim((string) ($row['secret_key'] ?? ''));

            $existing = $this->entityManager->getRepository(ProjectApiKey::class)->findOneBy(['publicKey' => $publicKey]);
            if ($existing instanceof ProjectApiKey) {
                ++$skipped;
                $io->writeln(\sprintf('  skip public_key already present (%s)', $publicKey));
                continue;
            }

            $project = $this->projectRepository->findOneBy(['code' => $code]);
            if (!$project instanceof Project) {
                throw new RuntimeException(\sprintf('Unknown project_code "%s" for keys[%d] — import bundle first', $code, $i));
            }

            $apiKey = $this->apiKeyFactory->create($project, '' !== $label ? $label : 'preload', $publicKey, $secretKey);
            $project->addApiKey($apiKey);
            $this->projectRepository->save($project);
            ++$created;
            $io->writeln(\sprintf('  created key label=%s project=%s public=%s', $apiKey->getLabel(), $code, $publicKey));
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonObject(string $path): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException(\sprintf('Cannot read %s', $path));
        }
        $raw = file_get_contents($path);
        if (false === $raw || '' === trim($raw)) {
            throw new RuntimeException(\sprintf('Empty file %s', $path));
        }
        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(\sprintf('Invalid JSON in %s: %s', $path, $e->getMessage()), 0, $e);
        }
        if (!\is_array($decoded)) {
            throw new RuntimeException(\sprintf('JSON root must be an object in %s', $path));
        }

        return $decoded;
    }
}
