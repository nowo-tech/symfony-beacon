<?php

declare(strict_types=1);

namespace App\Shared\Rbac;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves RBAC permission-category display name / description (REQ-RBAC-008).
 *
 * Keys: permissions.category.<slug>.name|description where slug = lowercase category code
 * stored on the permission row (e.g. access, org, custom). The slug itself is a machine id
 * and is not translated.
 */
final readonly class RbacPermissionCategoryTranslator
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function catalogSlug(string $category): string
    {
        return strtolower(trim($category));
    }

    public function nameKey(string $category): string
    {
        return 'permissions.category.'.$this->catalogSlug($category).'.name';
    }

    public function descriptionKey(string $category): string
    {
        return 'permissions.category.'.$this->catalogSlug($category).'.description';
    }

    /**
     * @param string|object{getCategory(): string} $categoryOrPermission
     */
    public function name(string|object $categoryOrPermission): string
    {
        $slug = $this->resolveSlug($categoryOrPermission);
        $key = $this->nameKey($slug);
        $translated = $this->translator->trans($key);
        if ($translated === $key) {
            return $slug;
        }

        return $translated;
    }

    /**
     * @param string|object{getCategory(): string} $categoryOrPermission
     */
    public function description(string|object $categoryOrPermission): ?string
    {
        $slug = $this->resolveSlug($categoryOrPermission);
        $key = $this->descriptionKey($slug);
        $translated = $this->translator->trans($key);
        if ($translated === $key) {
            return null;
        }

        $value = trim($translated);

        return '' === $value ? null : $value;
    }

    /**
     * @param string|object{getCategory(): string} $categoryOrPermission
     */
    private function resolveSlug(string|object $categoryOrPermission): string
    {
        if (\is_string($categoryOrPermission)) {
            return $this->catalogSlug($categoryOrPermission);
        }

        return $this->catalogSlug($categoryOrPermission->getCategory());
    }
}
