<?php

declare(strict_types=1);

namespace App\Shared\Appearance\Form;

use Symfony\Component\Validator\Constraint;
use App\Shared\Appearance\AppearanceSettingsSection;
use App\Shared\Appearance\AppearanceSettingsSubtab;
use App\Shared\Appearance\Entity\SiteAppearance;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Site branding / layout / palette — one field group per appearance tab.
 */
final class SiteAppearanceType extends FormKitAbstractType
{
    private const string HEX = '/^#[0-9a-fA-F]{6}$/';

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var AppearanceSettingsSection $section */
        $section = $options['section'];
        /** @var AppearanceSettingsSubtab|null $subtab */
        $subtab = $options['subtab'];
        $hex = [
            new NotBlank(),
            new Regex(pattern: self::HEX, message: 'site_appearance.color.invalid'),
        ];

        $this->withBuilder($builder, function () use ($section, $subtab, $hex): void {
            match ($section) {
                AppearanceSettingsSection::Brand => $this->addBrandFields(),
                AppearanceSettingsSection::Layout => $this->addLayoutFields(),
                AppearanceSettingsSection::Colors => $this->addColorFields($subtab, $hex),
                AppearanceSettingsSection::Themes => null,
            };
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SiteAppearance::class,
            'section' => AppearanceSettingsSection::Brand,
            'subtab' => null,
        ]);
        $resolver->setAllowedTypes('section', AppearanceSettingsSection::class);
        $resolver->setAllowedTypes('subtab', ['null', AppearanceSettingsSubtab::class]);
    }

    private function addBrandFields(): void
    {
        $this->addTextField('brandName', [
            'constraints' => [new NotBlank(), new Length(max: 80)],
        ]);
        $this->addTextField('brandEyebrow', [
            'constraints' => [new NotBlank(), new Length(max: 80)],
        ]);
    }

    private function addLayoutFields(): void
    {
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
    }

    /**
     * @param list<Constraint> $hex
     */
    private function addColorFields(?AppearanceSettingsSubtab $subtab, array $hex): void
    {
        match ($subtab) {
            AppearanceSettingsSubtab::Accents => $this->addAccentColorFields($hex),
            AppearanceSettingsSubtab::Status => $this->addStatusColorFields($hex),
            AppearanceSettingsSubtab::Surfaces => $this->addSurfaceColorFields($hex),
            default => $this->addAccentColorFields($hex),
        };
    }

    /**
     * @param list<Constraint> $hex
     */
    private function addAccentColorFields(array $hex): void
    {
        $this->addNamedField('accentColor', 'color', ['constraints' => $hex]);
        $this->addNamedField('accentDeepColor', 'color', ['constraints' => $hex]);
        $this->addNamedField('accentColorDark', 'color', ['constraints' => $hex]);
        $this->addNamedField('accentDeepColorDark', 'color', ['constraints' => $hex]);
    }

    /**
     * @param list<Constraint> $hex
     */
    private function addStatusColorFields(array $hex): void
    {
        $this->addNamedField('dangerColor', 'color', ['constraints' => $hex]);
        $this->addNamedField('dangerColorDark', 'color', ['constraints' => $hex]);
        $this->addNamedField('warnColor', 'color', ['constraints' => $hex]);
        $this->addNamedField('warnColorDark', 'color', ['constraints' => $hex]);
    }

    /**
     * @param list<Constraint> $hex
     */
    private function addSurfaceColorFields(array $hex): void
    {
        $this->addNamedField('paperColor', 'color', ['constraints' => $hex]);
        $this->addNamedField('paperColorDark', 'color', ['constraints' => $hex]);
        $this->addNamedField('inkColor', 'color', ['constraints' => $hex]);
        $this->addNamedField('inkColorDark', 'color', ['constraints' => $hex]);
        $this->addNamedField('surfaceColor', 'color', ['constraints' => $hex]);
        $this->addNamedField('surfaceColorDark', 'color', ['constraints' => $hex]);
    }
}
