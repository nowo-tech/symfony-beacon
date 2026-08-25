<?php

declare(strict_types=1);

namespace App\Project\Command;

use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Inventory and clear redundant legacy encrypted API secrets once SHA-256 hash exists.
 *
 * Keys that still authenticate only via the Halite {@code secret_key} column are reported
 * but not cleared — rotate them in Project Settings or wait for the next successful ingest.
 */
#[AsCommand(
    name: 'app:project:api-key-legacy-secrets',
    description: 'Report / clear redundant legacy encrypted project API secrets',
)]
final class ClearLegacyApiKeySecretsCommand extends Command
{
    public function __construct(
        private readonly ProjectApiKeyRepository $apiKeyRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Clear secret_key when secret_hash is already present');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        /** @var list<ProjectApiKey> $keys */
        $keys = $this->apiKeyRepository->findAll();
        $legacyOnly = 0;
        $redundant = 0;
        $cleared = 0;

        foreach ($keys as $key) {
            if (!$key->hasLegacyEncryptedSecret()) {
                continue;
            }
            $hash = $key->getSecretHash();
            if (null === $hash || '' === $hash) {
                ++$legacyOnly;
                $io->writeln(\sprintf(
                    '  legacy-only id=%s public=%s project=%s — rotate or wait for ingest upgrade',
                    (string) $key->getId(),
                    $key->getPublicKey(),
                    (string) $key->getProject()?->getId(),
                ));
                continue;
            }

            ++$redundant;
            if ($apply && $key->clearRedundantLegacySecret()) {
                ++$cleared;
            }
        }

        if ($apply) {
            $this->entityManager->flush();
            $io->success(\sprintf(
                'Cleared %d redundant legacy secret_key row(s). legacy-only remaining: %d.',
                $cleared,
                $legacyOnly,
            ));
        } else {
            $io->note(\sprintf(
                'Dry-run: %d key(s) with redundant secret_key+hash, %d legacy-only (need rotate/ingest). Re-run with --apply to clear redundant ciphertext.',
                $redundant,
                $legacyOnly,
            ));
        }

        return Command::SUCCESS;
    }
}
