<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Reusable admin directory search field ({@code q}) — FormKit {@code filter}.
 *
 * Twig should pass {@code attr.placeholder} already translated (e.g. {@code key|trans}).
 * {@code placeholder: false} skips FormKit auto-placeholder so Twig wins.
 */
final class AdminSearchType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('q', 'search', [
                'placeholder' => false,
                'help' => false,
                'translation_domain' => false,
                'attr' => [
                    'class' => 'input min-w-56 flex-1',
                    'autocomplete' => 'off',
                ],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'search_placeholder' => null,
        ]);
        $resolver->setAllowedTypes('search_placeholder', ['null', 'string']);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_search';
    }
}
