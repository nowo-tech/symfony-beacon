<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use App\Issues\IssuePanelIds;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\TagInputBundle\Form\TagType;
use Nowo\TagInputBundle\Form\ValueFormat;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Account display preferences: locale, theme, layout, a11y, issue panels.
 */
final class AccountDisplayType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $enabledLocales */
        $enabledLocales = $options['enabled_locales'];
        $localeChoices = [];
        foreach ($enabledLocales as $locale) {
            $localeChoices[strtoupper($locale)] = $locale;
        }

        $panelIds = IssuePanelIds::all();

        $this->withBuilder($builder, function () use ($localeChoices): void {
            $this->addChoiceField('preferredLocale', [
                'choices' => $localeChoices,
                'placeholder' => 'preferences.locale_auto',
                'required' => false,
            ]);
            $this->addChoiceField('preferredTheme', [
                'choices' => [
                    'preferences.theme_light' => 'light',
                    'preferences.theme_dark' => 'dark',
                ],
                'choice_translation_domain' => 'messages',
                'placeholder' => 'preferences.theme_auto',
                'required' => false,
            ]);
            $this->addChoiceField('preferredContentWidth', [
                'choices' => [
                    'preferences.width_content' => 'content',
                    'preferences.width_full' => 'full',
                ],
                'choice_translation_domain' => 'messages',
                'required' => true,
            ]);
            $this->addChoiceField('preferredUiDensity', [
                'choices' => [
                    'preferences.density_comfortable' => 'comfortable',
                    'preferences.density_compact' => 'compact',
                ],
                'choice_translation_domain' => 'messages',
                'required' => true,
            ]);
            $this->addChoiceField('preferredFontScale', [
                'choices' => [
                    'preferences.font_scale_sm' => 'sm',
                    'preferences.font_scale_md' => 'md',
                    'preferences.font_scale_lg' => 'lg',
                ],
                'choice_translation_domain' => 'messages',
                'required' => true,
            ]);
            $this->addChoiceField('preferredContrast', [
                'choices' => [
                    'preferences.contrast_more' => 'more',
                ],
                'choice_translation_domain' => 'messages',
                'placeholder' => 'preferences.contrast_system',
                'required' => false,
            ]);
            $this->addChoiceField('preferredSidebar', [
                'choices' => [
                    'preferences.sidebar_expanded' => 'expanded',
                    'preferences.sidebar_collapsed' => 'collapsed',
                ],
                'choice_translation_domain' => 'messages',
                'required' => true,
            ]);
            $this->addChoiceField('preferredMotion', [
                'choices' => [
                    'preferences.motion_reduce' => 'reduce',
                    'preferences.motion_full' => 'full',
                ],
                'choice_translation_domain' => 'messages',
                'placeholder' => 'preferences.motion_system',
                'required' => false,
            ]);
        });

        $builder->add('preferredCollapsedIssuePanels', TagType::class, [
            'value_format' => ValueFormat::ARRAY,
            'whitelist' => $panelIds,
            'max_tags' => \count($panelIds),
            'duplicates' => false,
            'dropdown_enabled' => true,
            'required' => false,
            'label' => 'preferences.issue_panels_collapsed',
            'help' => 'preferences.issue_panels_help',
            'placeholder' => 'preferences.issue_panels_placeholder',
            'translation_domain' => 'messages',
            'container_class' => 'nowo-tag-input issue-panel-prefs',
            'input_class' => 'input nowo-tag-input__field',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'enabled_locales' => ['en', 'es', 'de', 'nl', 'fr', 'it', 'pt'],
        ]);
        $resolver->setAllowedTypes('enabled_locales', 'string[]');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'user_preferences';
    }
}
