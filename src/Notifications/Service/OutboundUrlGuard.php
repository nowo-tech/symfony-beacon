<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Shared\Settings\Service\InstanceOpsDefaults;
use InvalidArgumentException;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard as KitOutboundUrlGuard;

/**
 * Blocks SSRF on outbound notification URLs via nowo-tech/outbound-url-guard-bundle.
 *
 * DNS is resolved and pinned. Cloud metadata stays blocked even when
 * {@see InstanceOpsDefaults::allowPrivateUrls()} is on. Callers must still set
 * HttpClient `max_redirects` to 0.
 *
 * FrankenPHP leaves {@see \PHP_BINARY} unable to run `php -r`. In that runtime
 * the lookup stays in-process so a public webhook is not rejected as unresolved.
 */
final readonly class OutboundUrlGuard
{
    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
        private HostnameDnsLookup $dnsLookup = new HostnameDnsLookup(),
        private PhpCliProbe $phpCli = new PhpCliProbe(),
    ) {
    }

    /**
     * @throws InvalidArgumentException when the URL is unsafe
     */
    public function assertSafeHttpUrl(string $url): void
    {
        $this->guard()->assertSafe($url);
    }

    /**
     * Validate the URL and return HttpClient options that pin DNS for hostname targets.
     *
     * @return array{resolve?: array<string, string>}
     *
     * @throws InvalidArgumentException when the URL is unsafe
     */
    public function httpClientOptionsForUrl(string $url): array
    {
        return $this->guard()->httpClientOptions($url);
    }

    private function guard(): KitOutboundUrlGuard
    {
        return new KitOutboundUrlGuard(
            allowPrivate: $this->opsDefaults->allowPrivateUrls(),
            resolveDns: true,
            dns: $this->dns(),
        );
    }

    private function dns(): HostnameDnsLookup
    {
        if (HostnameDnsLookup::class !== $this->dnsLookup::class || $this->phpCli->supportsDashR()) {
            return $this->dnsLookup;
        }

        return new InProcessHostnameDnsLookup();
    }
}
