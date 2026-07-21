<?php

declare(strict_types=1);

namespace App\Shared\Appearance\Form;

use App\Shared\Appearance\Entity\SiteAppearance;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Site branding / accent colors for ROLE_ADMIN operators.
 */
final class SiteAppearanceType extends FormKitAbstractType
{
    private const string HEX = '/^#[0-9a-fA-F]{6}$/';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hex = [
            new NotBlank(),
            new Regex(pattern: self::HEX, message: 'site_appearance.color.invalid'),
        ];

        $this->withBuilder($builder, function () use ($hex): void {
            $this->addTextField('brandName', [
                'constraints' => [new NotBlank(), new Length(max: 80)],
            ]);
            $this->addTextField('brandEyebrow', [
                'constraints' => [new NotBlank(), new Length(max: 80)],
            ]);
            $this->addNamedField('accentColor', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('accentDeepColor', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('accentColorDark', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('accentDeepColorDark', 'color', [
                'constraints' => $hex,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SiteAppearance::class,
        ]);
    }
}
