<?php

declare(strict_types=1);

namespace App\Shared\Rbac;

use App\Identity\Entity\InstanceRole;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves RBAC role display name / when-to-use description via Symfony translator
 * with entity column fallbacks (REQ-RBAC-008).
 *
 * Keys: roles.catalog.<slug>.name|description where slug = strtolower(code).
 */
final readonly class RbacRoleTranslator
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function catalogSlug(string $code): string
    {
        return strtolower($code);
    }

    public function nameKey(string $code): string
    {
        return 'roles.catalog.'.$this->catalogSlug($code).'.name';
    }

    public function descriptionKey(string $code): string
    {
        return 'roles.catalog.'.$this->catalogSlug($code).'.description';
    }

    public function name(InstanceRole $role): string
    {
        $key = $this->nameKey($role->getCode());
        $translated = $this->translator->trans($key);
        if ($translated === $key) {
            return $role->getName();
        }

        return $translated;
    }

    public function description(InstanceRole $role): ?string
    {
        $key = $this->descriptionKey($role->getCode());
        $translated = $this->translator->trans($key);
        if ($translated !== $key) {
            $value = trim($translated);

            return '' === $value ? null : $value;
        }

        $fallback = $role->getDescription();
        if (null === $fallback) {
            return null;
        }

        $fallback = trim($fallback);

        return '' === $fallback ? null : $fallback;
    }
}
