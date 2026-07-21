<?php

declare(strict_types=1);

namespace App\Shared\Locale;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Public dual URLs: default locale is bare (/setup), other locales are prefixed (/en/setup).
 */
final readonly class LocalizedPublicPath
{
    public const string SETUP = 'setup_wizard';
    public const string SETUP_LOCALIZED = 'setup_wizard_localized';
    public const string SETUP_RUN = 'setup_wizard_run';
    public const string SETUP_RUN_LOCALIZED = 'setup_wizard_run_localized';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $defaultLocale = 'en',
    ) {
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function isDefault(string $locale): bool
    {
        return $locale === $this->defaultLocale;
    }

    /**
     * @return array{_locale?: string}
     */
    public function routeParams(string $locale): array
    {
        return $this->isDefault($locale) ? [] : ['_locale' => $locale];
    }

    public function setupRouteName(string $locale): string
    {
        return $this->isDefault($locale) ? self::SETUP : self::SETUP_LOCALIZED;
    }

    public function setupRunRouteName(string $locale): string
    {
        return $this->isDefault($locale) ? self::SETUP_RUN : self::SETUP_RUN_LOCALIZED;
    }

    public function setupPath(string $locale, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->urlGenerator->generate(
            $this->setupRouteName($locale),
            $this->routeParams($locale),
            $referenceType,
        );
    }
}
