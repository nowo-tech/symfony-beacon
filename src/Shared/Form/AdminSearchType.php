<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Override;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Reusable admin directory search field (`q`).
 */
final class AdminSearchType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $placeholder = $options['search_placeholder'];

        $builder->add('q', SearchType::class, [
            'label' => false,
            'required' => false,
            'attr' => [
                'class' => 'input min-w-56 flex-1',
                'placeholder' => \is_string($placeholder) ? $placeholder : null,
                'autocomplete' => 'off',
            ],
        ]);
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
}
