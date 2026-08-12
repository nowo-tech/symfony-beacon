<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base type for idempotent GET filters rendered with FormKit.
 */
abstract class AbstractGetFilterType extends FormKitAbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
            'data_class' => null,
            'translation_domain' => 'messages',
        ]);
    }
}
