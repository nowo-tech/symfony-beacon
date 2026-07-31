<?php

declare(strict_types=1);

namespace App\Identity\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;

/**
 * Persists AuthKit social OAuth app credentials (admin CRUD).
 */
final readonly class SocialLoginCredentialSeeder
{
    /** @var list<string> */
    public const array BUILTIN_PROVIDERS = ['google', 'github', 'microsoft'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SocialLoginCredentialRepository $credentials,
    ) {
    }

    /**
     * @param list<string> $scopes
     */
    public function upsert(
        string $provider,
        string $label,
        string $clientId,
        string $clientSecret,
        bool $enabled,
        ?string $authorizeUrl = null,
        ?string $tokenUrl = null,
        ?string $userinfoUrl = null,
        array $scopes = [],
        bool $flush = true,
        bool $enterpriseSso = false,
    ): SocialLoginCredential {
        $credential = $this->credentials->findOneByProvider($provider);
        if (!$credential instanceof SocialLoginCredential) {
            $credential = new SocialLoginCredential()->setProvider($provider);
            $this->entityManager->persist($credential);
        }

        $credential
            ->setLabel('' !== $label ? $label : ucfirst($provider))
            ->setClientId($clientId)
            ->setClientSecret($clientSecret)
            ->setEnabled($enabled)
            ->setEnterpriseSso($enterpriseSso)
            ->setAuthorizeUrl($authorizeUrl)
            ->setTokenUrl($tokenUrl)
            ->setUserinfoUrl($userinfoUrl)
            ->setScopes($scopes);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $credential;
    }

    public function delete(SocialLoginCredential $credential): void
    {
        $this->entityManager->remove($credential);
        $this->entityManager->flush();
    }
}
