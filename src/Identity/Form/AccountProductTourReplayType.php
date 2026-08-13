<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-only form to clear product-tour seen flags and force a replay.
 */
final class AccountProductTourReplayType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // CSRF only
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'account_product_tour_replay',
        ]);
    }
}
