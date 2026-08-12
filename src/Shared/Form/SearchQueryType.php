<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Override;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Rootless GET search form (`q`) for kit dashboard / list filters.
 */
final class SearchQueryType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addTextField('q', [
                'required' => false,
                'empty_data' => '',
                'data' => (string) ($options['q'] ?? ''),
                'label' => false,
                'attr' => array_merge(['type' => 'search'], $options['input_attr']),
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
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
