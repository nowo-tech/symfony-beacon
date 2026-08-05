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
            $this->addCheckboxField('footerFixed', [
                'required' => false,
            ]);
            $this->addChoiceField('cornerStyle', [
                'choices' => [
                    'site_appearance.corner_style.choice.sharp' => SiteAppearance::CORNER_SHARP,
                    'site_appearance.corner_style.choice.soft' => SiteAppearance::CORNER_SOFT,
                    'site_appearance.corner_style.choice.rounded' => SiteAppearance::CORNER_ROUNDED,
                ],
                'choice_translation_domain' => 'messages',
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('borderStrength', [
                'choices' => [
                    'site_appearance.border_strength.choice.subtle' => SiteAppearance::BORDER_SUBTLE,
                    'site_appearance.border_strength.choice.medium' => SiteAppearance::BORDER_MEDIUM,
                    'site_appearance.border_strength.choice.strong' => SiteAppearance::BORDER_STRONG,
                ],
                'choice_translation_domain' => 'messages',
                'required' => true,
                'placeholder' => false,
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
            $this->addNamedField('dangerColor', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('dangerColorDark', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('warnColor', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('warnColorDark', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('paperColor', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('paperColorDark', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('inkColor', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('inkColorDark', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('surfaceColor', 'color', [
                'constraints' => $hex,
            ]);
            $this->addNamedField('surfaceColorDark', 'color', [
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
