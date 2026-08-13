<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use App\Identity\Tour\ProductTourPage;
use App\Issues\IssuePanelIds;
use App\Shared\Form\FormKitAbstractType;
use InvalidArgumentException;
use Nowo\TagInputBundle\Form\TagType;
use Nowo\TagInputBundle\Form\ValueFormat;
use Override;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Account display preferences by section: appearance, panels, tours, or notifications.
 */
final class AccountDisplayType extends FormKitAbstractType
{
    public const string SECTION_APPEARANCE = 'appearance';

    public const string SECTION_PANELS = 'panels';

    public const string SECTION_TOURS = 'tours';

    public const string SECTION_NOTIFICATIONS = 'notifications';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $enabledLocales */
        $enabledLocales = $options['enabled_locales'];
        $section = (string) $options['section'];

        match ($section) {
            self::SECTION_APPEARANCE => $this->buildAppearanceFields($builder, $enabledLocales),
            self::SECTION_PANELS => $this->buildPanelFields($builder),
            self::SECTION_TOURS => $this->buildTourFields($builder),
            self::SECTION_NOTIFICATIONS => $this->buildNotificationFields($builder, (bool) $options['push_available']),
            default => throw new InvalidArgumentException(\sprintf('Unknown display section "%s".', $section)),
        };
    }

    /**
     * @param FormBuilderInterface<User> $builder
     * @param list<string>               $enabledLocales
     */
    private function buildAppearanceFields(FormBuilderInterface $builder, array $enabledLocales): void
    {
        $localeChoices = [];
        foreach ($enabledLocales as $locale) {
            $localeChoices[strtoupper($locale)] = $locale;
        }

        $this->withBuilder($builder, function () use ($localeChoices): void {
            // No empty ChoiceType placeholder — every account has concrete defaults.
            $this->addChoiceField('preferredLocale', [
                'choices' => $localeChoices,
                // Literal locale codes (EN, ES, …) — do not translate as catalogue keys.
                'choice_translation_domain' => false,
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('preferredTheme', [
                'choices' => [
                    'preferences.theme_light' => 'light',
                    'preferences.theme_dark' => 'dark',
                ],
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('preferredContentWidth', [
                'choices' => [
                    'preferences.width_content' => 'content',
                    'preferences.width_full' => 'full',
                ],
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('preferredUiDensity', [
                'choices' => [
                    'preferences.density_comfortable' => 'comfortable',
                    'preferences.density_compact' => 'compact',
                ],
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('preferredFontScale', [
                'choices' => [
                    'preferences.font_scale_sm' => 'sm',
                    'preferences.font_scale_md' => 'md',
                    'preferences.font_scale_lg' => 'lg',
                ],
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('preferredContrast', [
                'choices' => [
                    'preferences.contrast_system' => 'system',
                    'preferences.contrast_more' => 'more',
                ],
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('preferredSidebar', [
                'choices' => [
                    'preferences.sidebar_expanded' => 'expanded',
                    'preferences.sidebar_collapsed' => 'collapsed',
                ],
                'required' => true,
                'placeholder' => false,
            ]);
            $this->addChoiceField('preferredMotion', [
                'choices' => [
                    'preferences.motion_system' => 'system',
                    'preferences.motion_reduce' => 'reduce',
                    'preferences.motion_full' => 'full',
                ],
                'required' => true,
                'placeholder' => false,
            ]);
        });
    }

    /**
     * @param FormBuilderInterface<User> $builder
     */
    private function buildPanelFields(FormBuilderInterface $builder): void
    {
        $panelIds = IssuePanelIds::all();

        $this->withBuilder($builder, function () use ($panelIds): void {
            $merged = $this->mergeFieldOptions('preferredCollapsedIssuePanels', 'text', [
                'value_format' => ValueFormat::ARRAY,
                'whitelist' => $panelIds,
                'max_tags' => \count($panelIds),
                'duplicates' => false,
                'dropdown_enabled' => true,
                'required' => false,
                'container_class' => 'nowo-tag-input issue-panel-prefs',
                'input_class' => 'input nowo-tag-input__field',
            ]);
            // TagType requires a root placeholder string; FormKit stores the key on attr.
            $attrPlaceholder = $merged['attr']['placeholder'] ?? null;
            if (\is_string($attrPlaceholder) && '' !== $attrPlaceholder) {
                $merged['placeholder'] = $attrPlaceholder;
            }

            $this->boundBuilder()->add('preferredCollapsedIssuePanels', TagType::class, $merged);
        });
    }

    /**
     * @param FormBuilderInterface<User> $builder
     */
    private function buildTourFields(FormBuilderInterface $builder): void
    {
        $tourChoices = [];
        foreach (ProductTourPage::all() as $page) {
            $tourChoices['preferences.product_tour_page.'.$page->value] = $page->value;
        }

        $this->withBuilder($builder, function () use ($tourChoices): void {
            $this->addChoiceField('productTourEnabledPages', [
                'mapped' => false,
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $tourChoices,
                'label' => 'preferences.product_tour_enabled',
                'help' => 'preferences.product_tour_enabled_help',
                'select_all' => true,
                'select_all_label' => 'preferences.product_tour_select_all',
                'select_all_translation_domain' => 'form',
                'select_all_css_class' => 'size-4 shrink-0 rounded border-[var(--color-sand)] text-[var(--color-moss)] focus:ring-[var(--color-moss)]/30',
                'select_all_wrapper_css_class' => 'flex items-center gap-2.5 pb-2 mb-1 border-b border-[var(--color-sand)]/60',
                'select_all_label_css_class' => 'text-sm font-medium text-[var(--color-ink)]',
                'select_all_container_css_class' => 'space-y-3',
            ]);
        });
    }

    /**
     * @param FormBuilderInterface<User> $builder
     */
    private function buildNotificationFields(FormBuilderInterface $builder, bool $pushAvailable): void
    {
        if (!$pushAvailable) {
            return;
        }

        $builder->add('pushNotificationsEnabled', CheckboxType::class, [
            'required' => false,
            'label' => 'preferences.push_notifications',
            'help' => 'preferences.push_notifications_help',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'enabled_locales' => ['en', 'es', 'de', 'nl', 'fr', 'it', 'pt'],
            'push_available' => false,
            'section' => self::SECTION_APPEARANCE,
        ]);
        $resolver->setAllowedTypes('enabled_locales', 'string[]');
        $resolver->setAllowedTypes('push_available', 'bool');
        $resolver->setAllowedTypes('section', 'string');
        $resolver->setAllowedValues('section', [
            self::SECTION_APPEARANCE,
            self::SECTION_PANELS,
            self::SECTION_TOURS,
            self::SECTION_NOTIFICATIONS,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'user_preferences';
    }
}
