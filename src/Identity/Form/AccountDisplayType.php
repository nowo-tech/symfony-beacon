<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use App\Identity\Tour\ProductTourPage;
use App\Issues\IssuePanelIds;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\TagInputBundle\Form\TagType;
use Nowo\TagInputBundle\Form\ValueFormat;
use Override;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Account display preferences: locale, theme, layout, a11y, issue panels, tours, push.
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
        $tourChoices = [];
        foreach (ProductTourPage::all() as $page) {
            $tourChoices['preferences.product_tour_page.'.$page->value] = $page->value;
        }

        $this->withBuilder($builder, function () use ($localeChoices, $tourChoices): void {
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

            $this->addChoiceField('productTourEnabledPages', [
                'mapped' => false,
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $tourChoices,
                'choice_translation_domain' => 'messages',
                'label' => 'preferences.product_tour_enabled',
                'help' => 'preferences.product_tour_enabled_help',
                'select_all' => true,
                'select_all_label' => 'preferences.product_tour_select_all',
                'select_all_translation_domain' => 'messages',
                'select_all_css_class' => 'size-4 rounded border-[var(--color-sand)] text-[var(--color-moss)] focus:ring-[var(--color-moss)]/30',
                'select_all_wrapper_css_class' => 'flex items-center gap-2',
                'select_all_label_css_class' => 'text-sm font-medium text-[var(--color-ink)]',
                'select_all_container_css_class' => 'space-y-2',
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

        if ($options['push_available']) {
            $builder->add('pushNotificationsEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'preferences.push_notifications',
                'help' => 'preferences.push_notifications_help',
                'translation_domain' => 'messages',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'enabled_locales' => ['en', 'es', 'de', 'nl', 'fr', 'it', 'pt'],
            'push_available' => false,
        ]);
        $resolver->setAllowedTypes('enabled_locales', 'string[]');
        $resolver->setAllowedTypes('push_available', 'bool');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'user_preferences';
    }
}
