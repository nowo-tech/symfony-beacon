<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Rootless GET search form ({@code q}) for kit dashboard / list filters.
 *
 * Uses FormKit profile {@code filter} via {@see AbstractGetFilterType} — not {@code beacon}.
 * Callers pass {@code attr.placeholder} already translated when needed.
 */
final class SearchQueryType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addNamedField('q', 'search', [
                'empty_data' => '',
                'data' => (string) ($options['q'] ?? ''),
                'placeholder' => false,
                'help' => false,
                'translation_domain' => false,
                'attr' => array_merge(['type' => 'search'], $options['input_attr']),
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'q' => '',
            'input_attr' => [],
        ]);
        $resolver->setAllowedTypes('q', 'string');
        $resolver->setAllowedTypes('input_attr', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
