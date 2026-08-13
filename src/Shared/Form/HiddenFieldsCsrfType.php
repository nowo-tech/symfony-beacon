<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-protected POST form with typed fields (replaces raw Twig {@code <input>} markup).
 *
 * Pass field names via {@see OptionsResolver} {@code fields} and values as the form data array.
 * Optional {@code field_types} / {@code field_options} override the default hidden widgets
 * (e.g. {@code label} => {@code text} for Site Backup create).
 * Empty block prefix keeps submitted keys flat ({@code enabled}, {@code redirect}, …).
 */
final class HiddenFieldsCsrfType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $fields */
        $fields = $options['fields'];
        /** @var array<string, string> $fieldTypes */
        $fieldTypes = $options['field_types'];
        /** @var array<string, array<string, mixed>> $fieldOptions */
        $fieldOptions = $options['field_options'];

        $this->withBuilder($builder, function () use ($fields, $fieldTypes, $fieldOptions): void {
            foreach ($fields as $name) {
                $type = $fieldTypes[$name] ?? 'hidden';
                $defaults = [
                    'required' => false,
                    'empty_data' => '',
                    'label' => false,
                    'help' => false,
                    'placeholder' => false,
                    'translation_domain' => false,
                ];
                /** @var array<string, mixed> $extra */
                $extra = $fieldOptions[$name] ?? [];
                $this->addNamedField($name, $type, array_replace($defaults, $extra));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'csrf_only',
            'translation_domain' => false,
            'fields' => [],
            'field_types' => [],
            'field_options' => [],
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
        $resolver->setAllowedTypes('fields', 'string[]');
        $resolver->setAllowedTypes('field_types', 'array');
        $resolver->setAllowedTypes('field_options', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
