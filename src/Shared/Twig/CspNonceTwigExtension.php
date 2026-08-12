<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Http\ContentSecurityPolicySubscriber;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the per-request CSP nonce for the site appearance {@code <style>} block.
 */
final class CspNonceTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->nonce(...)),
        ];
    }

    public function nonce(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return '';
        }

        return (string) $request->attributes->get(ContentSecurityPolicySubscriber::REQUEST_ATTR_NONCE, '');
    }
}
