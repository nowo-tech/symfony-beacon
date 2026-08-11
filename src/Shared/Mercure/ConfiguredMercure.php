<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Optional Mercure hub driven by Administration → Mercure (env fallbacks for URL/secret).
 */
final class ConfiguredMercure implements ResetInterface
{
    private ?InstanceSettings $settings = null;
    private ?Hub $hub = null;

    public function __construct(
        private readonly InstanceSettingsRepository $settingsRepository,
        #[Autowire('%beacon.mercure.env_url%')]
        private readonly string $envUrl,
        #[Autowire('%beacon.mercure.env_public_url%')]
        private readonly string $envPublicUrl,
        #[Autowire('%beacon.mercure.env_jwt_secret%')]
        private readonly string $envJwtSecret,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function reset(): void
    {
        $this->settings = null;
        $this->hub = null;
    }

    public function isEnabled(): bool
    {
        if (!$this->settings()->isMercureEnabled()) {
            return false;
        }

        return '' !== $this->resolvedUrl() && '' !== $this->resolvedJwtSecret();
    }

    public function getPublicUrl(): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $public = $this->resolvedPublicUrl();

        return '' !== $public ? $public : $this->resolvedUrl();
    }

    /**
     * @param list<string> $topics
     */
    public function createSubscriberToken(array $topics): ?string
    {
        if (!$this->isEnabled() || [] === $topics) {
            return null;
        }

        $secret = $this->resolvedJwtSecret();
        if ('' === $secret) {
            return null;
        }

        /** @var non-empty-string $secret */
        $factory = new LcobucciFactory($secret);

        // symfony/mercure ≥0.8: create() takes Grant objects (not topic string lists).
        return $factory->create([
            new Grant([Grant::ACTION_SUBSCRIBE], $topics),
        ]);
    }

    public function publish(Update $update): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->hub()->publish($update);
    }

    public function isUsingDatabaseSecret(): bool
    {
        return null !== $this->usableSecret($this->settings()->getMercureJwtSecret());
    }

    public function isUsingDatabaseUrl(): bool
    {
        return null !== $this->usableHttpUrl($this->settings()->getMercureUrl());
    }

    public function envUrlConfigured(): bool
    {
        return null !== $this->usableHttpUrl($this->envUrl);
    }

    public function envJwtConfigured(): bool
    {
        return null !== $this->usableSecret($this->envJwtSecret);
    }

    private function hub(): Hub
    {
        if ($this->hub instanceof Hub) {
            return $this->hub;
        }

        $secret = $this->resolvedJwtSecret();
        $url = $this->resolvedUrl();
        if ('' === $secret || '' === $url) {
            throw new RuntimeException('Mercure is not configured.');
        }

        /** @var non-empty-string $secret */
        $factory = new LcobucciFactory($secret);
        $provider = new FactoryTokenProvider($factory, [
            new Grant([Grant::ACTION_PUBLISH], ['*']),
        ]);
        $public = $this->resolvedPublicUrl();
        $client = $this->httpClient ?? HttpClient::create();

        $this->hub = new Hub(
            $url,
            $provider,
            $factory,
            '' !== $public ? $public : null,
            $client,
        );

        return $this->hub;
    }

    private function settings(): InstanceSettings
    {
        return $this->settings ??= $this->settingsRepository->getOrCreate();
    }

    private function resolvedUrl(): string
    {
        return $this->usableHttpUrl($this->settings()->getMercureUrl())
            ?? $this->usableHttpUrl($this->envUrl)
            ?? '';
    }

    private function resolvedPublicUrl(): string
    {
        return $this->usableHttpUrl($this->settings()->getMercurePublicUrl())
            ?? $this->usableHttpUrl($this->envPublicUrl)
            ?? '';
    }

    private function resolvedJwtSecret(): string
    {
        return $this->usableSecret($this->settings()->getMercureJwtSecret())
            ?? $this->usableSecret($this->envJwtSecret)
            ?? '';
    }

    /**
     * Reject empty values and Halite ciphertext left behind when decrypt fails
     * (encrypt subscriber strips the `<ENC>` marker but cannot recover plaintext).
     */
    private function usableHttpUrl(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ('' === $trimmed || $this->looksLikeUndecryptedCiphertext($trimmed)) {
            return null;
        }
        if (false === filter_var($trimmed, \FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($trimmed, \PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true) ? $trimmed : null;
    }

    private function usableSecret(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ('' === $trimmed || $this->looksLikeUndecryptedCiphertext($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function looksLikeUndecryptedCiphertext(string $value): bool
    {
        // Halite sealed messages in this bundle commonly start with "MUIF" + base64url.
        return 1 === preg_match('/^MUIF[A-Za-z0-9_-]+={0,2}$/', $value);
    }
}
