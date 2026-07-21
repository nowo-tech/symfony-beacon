<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Locale\LocalizedPublicPath;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LocalizedPublicPathExtension extends AbstractExtension
{
    public function __construct(
        private readonly LocalizedPublicPath $localizedPublicPath,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('beacon_setup_path', $this->setupPath(...)),
            new TwigFunction('beacon_setup_route', $this->setupRoute(...)),
            new TwigFunction('beacon_setup_run_route', $this->setupRunRoute(...)),
            new TwigFunction('beacon_setup_route_params', $this->setupRouteParams(...)),
        ];
    }

    public function setupPath(?string $locale = null): string
    {
        return $this->localizedPublicPath->setupPath($locale ?? $this->localizedPublicPath->defaultLocale());
    }

    public function setupRoute(?string $locale = null): string
    {
        return $this->localizedPublicPath->setupRouteName($locale ?? $this->localizedPublicPath->defaultLocale());
    }

    public function setupRunRoute(?string $locale = null): string
    {
        return $this->localizedPublicPath->setupRunRouteName($locale ?? $this->localizedPublicPath->defaultLocale());
    }

    /**
     * @return array{_locale?: string}
     */
    public function setupRouteParams(?string $locale = null): array
    {
        return $this->localizedPublicPath->routeParams($locale ?? $this->localizedPublicPath->defaultLocale());
    }
}
