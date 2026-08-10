<?php

declare(strict_types=1);

namespace App\Shared\Rbac;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves RBAC permission display name / description (REQ-RBAC-008).
 *
 * Order: Translatable row (permission_translation) → permissions.catalog.<slug>.* YAML → entity name/description.
 */
final readonly class RbacPermissionTranslator
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function catalogSlug(string $key): string
    {
        return strtolower(str_replace('.', '_', trim($key)));
    }

    public function nameKey(string $key): string
    {
        $slug = $this->catalogSlug($key);
        if ('' === $slug) {
            return '';
        }

        return 'permissions.catalog.'.$slug.'.name';
    }

    public function descriptionKey(string $key): string
    {
        $slug = $this->catalogSlug($key);
        if ('' === $slug) {
            return '';
        }

        return 'permissions.catalog.'.$slug.'.description';
    }

    /**
     * @param object{getKey(): string, getName(): string} $permission
     */
    public function name(object $permission): string
    {
        $locale = $this->translator->getLocale();
        if (\is_callable([$permission, 'getNameForLocale'])) {
            /** @var callable(string): ?string $getter */
            $getter = [$permission, 'getNameForLocale'];
            $localized = $getter($locale);
            if (\is_string($localized) && '' !== trim($localized)) {
                return trim($localized);
            }
        }

        $machineKey = trim($permission->getKey());
        if ('' === $machineKey) {
            return $permission->getName();
        }

        $key = $this->nameKey($machineKey);
        $translated = $this->translator->trans($key);
        if ($translated === $key) {
            return $permission->getName();
        }

        return $translated;
    }

    /**
     * @param object{getKey(): string, getDescription(): ?string} $permission
     */
    public function description(object $permission): ?string
    {
        $locale = $this->translator->getLocale();
        if (\is_callable([$permission, 'getDescriptionForLocale'])) {
            /** @var callable(string): ?string $getter */
            $getter = [$permission, 'getDescriptionForLocale'];
            $localized = $getter($locale);
            if (\is_string($localized) && '' !== trim($localized)) {
                return trim($localized);
            }
        }

        $machineKey = trim($permission->getKey());
        if ('' !== $machineKey) {
            $key = $this->descriptionKey($machineKey);
            $translated = $this->translator->trans($key);
            if ($translated !== $key) {
                $value = trim($translated);

                return '' === $value ? null : $value;
            }
        }

        $fallback = $permission->getDescription();
        if (null === $fallback) {
            return null;
        }

        $fallback = trim($fallback);

        return '' === $fallback ? null : $fallback;
    }
}
