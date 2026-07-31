<?php

declare(strict_types=1);

namespace App\Identity\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;

/**
 * Upserts AuthKit social OAuth app credentials from environment variables.
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

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SocialLoginCredentialRepository $credentials,
    ) {
    }

    /**
     * @return list<string> providers upserted
     */
    public function seedFromEnv(): array
    {
        $updated = [];

        foreach (self::PROVIDERS as $provider) {
            $prefix = 'AUTH_KIT_SOCIAL_'.strtoupper($provider).'_';
            $clientId = trim((string) ($_ENV[$prefix.'CLIENT_ID'] ?? $_SERVER[$prefix.'CLIENT_ID'] ?? getenv($prefix.'CLIENT_ID') ?: ''));
            $clientSecret = trim((string) ($_ENV[$prefix.'CLIENT_SECRET'] ?? $_SERVER[$prefix.'CLIENT_SECRET'] ?? getenv($prefix.'CLIENT_SECRET') ?: ''));

            if ('' === $clientId || '' === $clientSecret) {
                continue;
            }

            $label = trim((string) ($_ENV[$prefix.'LABEL'] ?? $_SERVER[$prefix.'LABEL'] ?? getenv($prefix.'LABEL') ?: ''));
            if ('' === $label) {
                $label = ucfirst($provider);
            }

            $enabledRaw = strtolower(trim((string) ($_ENV[$prefix.'ENABLED'] ?? $_SERVER[$prefix.'ENABLED'] ?? getenv($prefix.'ENABLED') ?: '1')));
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
