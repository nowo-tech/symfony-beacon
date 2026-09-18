<?php

declare(strict_types=1);

namespace App\Shared\Legal;

/**
 * Public legal pages an operator can override per locale (no repository fork).
 */
final class LegalPageCatalog
{
    public const string SLUG_REQUIREMENT = 'notice|privacy|terms|cookies';

    public const string LOCALE_REQUIREMENT = 'en|es|de|nl|fr|it|pt';

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return ['notice', 'privacy', 'terms', 'cookies'];
    }

    /**
     * @return list<string>
     */
    public static function locales(): array
    {
        return ['en', 'es', 'de', 'nl', 'fr', 'it', 'pt'];
    }
}
