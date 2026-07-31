<?php

declare(strict_types=1);

namespace App\Identity\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Upserts AuthKit social OAuth app credentials from container parameters (env-backed).
 *
 * Env pattern (empty values are skipped):
 * - AUTH_KIT_SOCIAL_{PROVIDER}_CLIENT_ID
 * - AUTH_KIT_SOCIAL_{PROVIDER}_CLIENT_SECRET
 * - AUTH_KIT_SOCIAL_{PROVIDER}_LABEL (optional)
 * - AUTH_KIT_SOCIAL_{PROVIDER}_ENABLED (optional, default true when both id/secret set)
 */
final readonly class SocialLoginCredentialSeeder
{
    /** @var list<string> */
    private const array PROVIDERS = ['google', 'github', 'microsoft'];

    /**
     * @param array<string, array{client_id: string, client_secret: string, label: string, enabled: string}> $providers
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SocialLoginCredentialRepository $credentials,
        #[Autowire('%beacon.auth_kit_social_providers%')]
        private array $providers,
    ) {
    }

    /**
     * @return list<string> providers upserted
     */
    public function seedFromEnv(): array
    {
        $updated = [];

        foreach (self::PROVIDERS as $provider) {
            $row = $this->providers[$provider] ?? null;
            if (!\is_array($row)) {
                continue;
            }

            $clientId = trim((string) ($row['client_id'] ?? ''));
            $clientSecret = trim((string) ($row['client_secret'] ?? ''));

            if ('' === $clientId || '' === $clientSecret) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ('' === $label) {
                $label = ucfirst($provider);
            }

            $enabledRaw = strtolower(trim((string) ($row['enabled'] ?? '1')));
            $enabled = !\in_array($enabledRaw, ['0', 'false', 'no', 'off'], true);

            $credential = $this->credentials->findOneByProvider($provider);
            if (!$credential instanceof SocialLoginCredential) {
                $credential = new SocialLoginCredential()->setProvider($provider);
                $this->entityManager->persist($credential);
            }

            $credential
                ->setLabel($label)
                ->setClientId($clientId)
                ->setClientSecret($clientSecret)
                ->setEnabled($enabled);

            $updated[] = $provider;
        }

        if ([] !== $updated) {
            $this->entityManager->flush();
        }

        return $updated;
    }
}
