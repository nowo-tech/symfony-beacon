<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Reusable CSRF-only Symfony form for single-action POSTs (toggle, revoke, delete, …).
 *
 * Pass a unique {@see OptionsResolver} `csrf_token_id` when creating the form.
 */
final class CsrfOnlyType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // CSRF only
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'csrf_only',
            'allow_extra_fields' => true,
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
