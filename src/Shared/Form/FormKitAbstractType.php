<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType as NowoFormKitAbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Beacon product forms — FormKit profile {@code beacon} (default_profile).
 *
 * Field catalogues: {@code translations/form.*.yaml}. Do not set
 * {@code translation_domain} on fields: {@see FormOptionsMerger} takes it from
 * the active profile ({@code beacon} / {@code filter} / kit profiles).
 *
 * Do not use this base for GET / list filters. Those must extend
 * {@see AbstractGetFilterType} so they resolve FormKit profile {@code filter}
 * ({@code #[FormKitConfig('filter')]}): never label, always placeholder, always help (unless {@code help: false}).
 */
abstract class FormKitAbstractType extends NowoFormKitAbstractType
{
    /**
     * Fields whose visible copy is supplied by Twig ({@code placeholder: 'key'|trans}).
     *
     * Symfony’s form theme re-runs {@code |trans} on {@code attr.placeholder} / {@code title}
     * using the field {@code translation_domain}. Pre-translated strings must use
     * {@code translation_domain: false} or they are looked up again in {@code form}.
     *
     * @return array{label: false, help: false, placeholder: false, translation_domain: false}
     */
    protected function twigOwnedChromeOptions(): array
    {
        return [
            'label' => false,
            'help' => false,
            'placeholder' => false,
            'translation_domain' => false,
        ];
    }

    /**
     * Choice empty-option from the {@code form} catalogue (FormKit moves root
     * {@code placeholder} to {@code attr}; restore ChoiceType empty option after merge).
     *
     * @param array<string, mixed> $options
     */
    protected function addChoiceWithFormPlaceholder(string $name, array $options): void
    {
        $emptyOption = $options['placeholder'] ?? false;
        unset($options['placeholder']);
        $options['placeholder'] = false;
        $options['label'] ??= false;
        $options['help'] ??= false;

        $merged = $this->mergeFieldOptions($name, 'choice', $options);
        if (false !== $emptyOption) {
            $merged['placeholder'] = $emptyOption;
            // Profile merger already set field domain to form; keep it for the empty option key.
            $merged['translation_domain'] = $options['translation_domain'] ?? 'form';
            if (isset($merged['attr']) && \is_array($merged['attr'])) {
                unset($merged['attr']['placeholder']);
            }
        }

        $this->boundBuilder()->add($name, ChoiceType::class, $merged);
    }
}
