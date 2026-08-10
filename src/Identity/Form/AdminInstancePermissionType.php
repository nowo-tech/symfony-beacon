<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\InstancePermission;
use App\Identity\Security\Permission;
use App\Identity\Service\InstancePermissionCategoryCatalog;
use App\Shared\Rbac\RbacPermissionTranslator;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin create/edit form for a custom (or catalog) instance permission.
 */
final class AdminInstancePermissionType extends FormKitAbstractType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly TranslatorInterface $translator,
        private readonly RbacPermissionTranslator $rbacPermissionTranslator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $locales */
        $locales = $options['enabled_locales'];
        $defaultLocale = $options['default_locale'];

        $this->withBuilder($builder, function () use ($options, $locales, $defaultLocale): void {
            $this->addTextField('key', [
                'label' => 'permissions.key_label',
                'help' => 'permissions.key_help',
                'placeholder' => 'admin_instance_permission.key.placeholder',
                'disabled' => (bool) $options['key_locked'],
                'constraints' => $options['key_locked'] ? [] : [
                    new NotBlank(),
                    new Length(max: 120),
                    new Regex(
                        pattern: Permission::KEY_PATTERN,
                        message: 'permissions.key_invalid',
                    ),
                ],
            ]);
            $this->addChoiceField('category', [
                'label' => 'permissions.category_label',
                'help' => 'permissions.category_help',
                'choices' => InstancePermissionCategoryCatalog::formChoices(),
                'constraints' => [
                    new NotBlank(),
                    new Choice(choices: InstancePermissionCategoryCatalog::slugs()),
                ],
            ]);

            foreach ($locales as $locale) {
                $nameConstraints = [new Length(max: 120)];
                if ($locale === $defaultLocale) {
                    $nameConstraints[] = new NotBlank();
                }
                $localeParam = ['%locale%' => strtoupper($locale)];
                $this->addTextField('name_'.$locale, [
                    'mapped' => false,
                    'required' => $locale === $defaultLocale,
                    'label' => 'permissions.name_label',
                    'help' => $locale === $defaultLocale
                        ? 'permissions.name_help'
                        : 'permissions.name_locale_help',
                    'help_translation_parameters' => $localeParam,
                    'constraints' => $nameConstraints,
                ]);
                $this->addTextareaField('description_'.$locale, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'permissions.description_label',
                    'help' => 'permissions.description_locale_help',
                    'help_translation_parameters' => $localeParam,
                    'constraints' => [new Length(max: 2000)],
                ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($locales, $defaultLocale): void {
            $permission = $event->getData();
            if (!$permission instanceof InstancePermission) {
                return;
            }
            $form = $event->getForm();
            foreach ($locales as $locale) {
                $name = $permission->getNameForLocale($locale);
                if (null === $name || '' === $name) {
                    if ($locale === $defaultLocale && '' !== $permission->getName()) {
                        $name = $permission->getName();
                    } elseif ('' !== $permission->getKey()) {
                        $catalogKey = $this->rbacPermissionTranslator->nameKey($permission->getKey());
                        $fromCatalog = $this->translator->trans($catalogKey, [], 'messages', $locale);
                        $name = $fromCatalog !== $catalogKey ? $fromCatalog : '';
                    } else {
                        $name = '';
                    }
                }
                $description = $permission->getDescriptionForLocale($locale);
                if (null === $description || '' === $description) {
                    if ($locale === $defaultLocale && null !== $permission->getDescription()) {
                        $description = $permission->getDescription();
                    } elseif ('' !== $permission->getKey()) {
                        $catalogKey = $this->rbacPermissionTranslator->descriptionKey($permission->getKey());
                        $fromCatalog = $this->translator->trans($catalogKey, [], 'messages', $locale);
                        $description = $fromCatalog !== $catalogKey ? $fromCatalog : '';
                    } else {
                        $description = '';
                    }
                }
                if ($form->has('name_'.$locale)) {
                    $form->get('name_'.$locale)->setData($name);
                }
                if ($form->has('description_'.$locale)) {
                    $form->get('description_'.$locale)->setData($description);
                }
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($locales, $defaultLocale): void {
            $permission = $event->getData();
            if (!$permission instanceof InstancePermission) {
                return;
            }
            $form = $event->getForm();
            $names = [];
            $descriptions = [];
            foreach ($locales as $locale) {
                $name = trim((string) $form->get('name_'.$locale)->getData());
                if ('' !== $name) {
                    $names[$locale] = $name;
                }
                $description = trim((string) $form->get('description_'.$locale)->getData());
                if ('' !== $description) {
                    $descriptions[$locale] = $description;
                }
            }
            $permission->syncTranslations($names, $descriptions);
            $fallbackName = $names[$defaultLocale] ?? (array_values($names)[0] ?? $permission->getName());
            $permission->setName($fallbackName);
            $fallbackDescription = $descriptions[$defaultLocale] ?? (array_values($descriptions)[0] ?? null);
            $permission->setDescription($fallbackDescription);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstancePermission::class,
            'csrf_protection' => true,
            'translation_domain' => 'messages',
            'key_locked' => false,
            // Controllers must pass `%default_locale%` (`DEFAULT_LOCALE`) + enabled locales.
            'enabled_locales' => [],
            'default_locale' => '',
        ]);
        $resolver->setAllowedTypes('key_locked', 'bool');
        $resolver->setAllowedTypes('enabled_locales', 'string[]');
        $resolver->setAllowedTypes('default_locale', 'string');
        $resolver->setRequired(['enabled_locales', 'default_locale']);
    }

    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['enabled_locales'] = $options['enabled_locales'];
        $view->vars['default_locale'] = $options['default_locale'];
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_instance_permission';
    }
}
