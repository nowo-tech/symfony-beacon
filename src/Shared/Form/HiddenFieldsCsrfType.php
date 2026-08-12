<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-protected POST form with typed hidden fields (replaces raw Twig {@code <input type="hidden">}).
 *
 * Pass field names via {@see OptionsResolver} {@code fields} and values as the form data array.
 * Empty block prefix keeps submitted keys flat ({@code enabled}, {@code redirect}, …).
 */
final class HiddenFieldsCsrfType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $fields */
        $fields = $options['fields'];

        $this->withBuilder($builder, function () use ($fields): void {
            foreach ($fields as $name) {
                $this->addNamedField($name, 'hidden', [
                    'required' => false,
                    'empty_data' => '',
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'csrf_only',
            'fields' => [],
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
        $resolver->setAllowedTypes('fields', 'string[]');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
