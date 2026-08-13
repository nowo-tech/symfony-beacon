<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * FormKit's FormOptionsMerger always injects help / help_attr (and may leave
 * placeholder) into merged field options. Symfony ButtonType / SubmitType do
 * not define those options, which breaks AuthKit login shells that embed
 * CookieConsent (and any other FormKit submit buttons).
 *
 * Accept the options as no-ops so kit profiles stay usable without a vendor patch.
 */
final class ButtonTypeFormKitCompatExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ButtonType::class, SubmitType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['help', 'help_attr', 'placeholder']);
        $resolver->setAllowedTypes('help', ['null', 'string', 'bool']);
        $resolver->setAllowedTypes('help_attr', 'array');
        $resolver->setAllowedTypes('placeholder', ['null', 'string', 'bool']);
    }
}
