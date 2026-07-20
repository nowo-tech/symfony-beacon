<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Account display preferences: locale, theme, and content width.
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
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'enabled_locales' => ['en', 'es'],
        ]);
        $resolver->setAllowedTypes('enabled_locales', 'string[]');
    }

    public function getBlockPrefix(): string
    {
        return 'user_preferences';
    }
}
