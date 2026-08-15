<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use App\Shared\Form\FormKitAbstractType;
use Nowo\PhoneInputBundle\Form\FlagDisplay;
use Nowo\PhoneInputBundle\Form\PrefixDisplay;
use Nowo\PhoneInputBundle\Form\Type\PhoneType;
use Nowo\PhoneInputBundle\Form\ValueFormat;
use Nowo\PhoneInputBundle\Validation\PhoneValidationMode;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Account profile basics: display name and phone (no password).
 *
 * Sensitive login identifiers live on {@see AccountProfileSensitiveType}.
 * Phone uses nowo-tech/phone-input-bundle with country prefix (E.164 → User.phone).
 */
final class AccountProfileType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('displayName', [
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->boundBuilder()->add(
                'phone',
                PhoneType::class,
                $this->mergeFieldOptions('phone', 'tel', [
                    'required' => false,
                    'country_prefix_selector' => true,
                    'value_format' => ValueFormat::CONCATENATED,
                    'default_country' => 'ES',
                    'preferred_countries' => ['ES', 'PT', 'FR', 'GB', 'DE', 'IT', 'US', 'MX', 'AR', 'CL', 'CO', 'PE'],
                    'prefix_display' => PrefixDisplay::FLAG_AND_PREFIX,
                    'flag_display' => FlagDisplay::CSS_ICON,
                    'prefix_search' => true,
                    'phone_validation' => PhoneValidationMode::COUNTRY,
                    'container_classes' => ['nowo-phone-input'],
                    'prefix_selector_classes' => ['nowo-phone-input__prefix'],
                    'national_number_classes' => ['input', 'nowo-phone-input__number'],
                    'constraints' => [new Length(max: 32)],
                ]),
            );
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'user_preferences';
    }
}
