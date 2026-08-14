<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Override;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Base type for idempotent GET / list filters.
 *
 * Always uses FormKit profile {@code filter} ({@see config/packages/nowo_form_kit.yaml}),
 * not {@code beacon}:
 * - label: never ({@code defaults.label: false})
 * - placeholder: always ({@code auto_placeholder}; catalogue {@code {prefix}.{field}.placeholder})
 * - help: always ({@code auto_help}; catalogue {@code {prefix}.{field}.help}), unless the
 *   field passes {@code help: false} (removed by FormKit merger)
 * - required: always {@code false} ({@code defaults.required: false}), except {@code per_page}
 *   ({@code required: true} in field options)
 *
 * Extend this class (do not put {@code #[FormKitConfig('filter')]} on ad-hoc types
 * that extend {@see FormKitAbstractType} alone).
 */
#[FormKitConfig('filter')]
abstract class AbstractGetFilterType extends FormKitAbstractType
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'csrf_protection' => false,
            'method' => 'GET',
            'data_class' => null,
        ]);
    }

    /**
     * Hidden field without FormKit auto placeholder/help (pass {@code false} in options so
     * the merger unsets them — {@code HiddenType} rejects bool {@code help}).
     *
     * @param array<string, mixed> $options
     */
    protected function addHiddenFilterField(string $name, array $options = []): void
    {
        $options['placeholder'] = false;
        $options['help'] = false;
        $this->addNamedField($name, 'hidden', $options);
    }

    /**
     * Choice fields with FormKit defaults. Empty option uses catalogue
     * {@code {block_prefix}.{field}.placeholder} by default (filter {@code auto_placeholder}).
     * Pass {@code placeholder: false} to omit the empty option, or an explicit key to override.
     *
     * @param array<string, mixed> $options
     */
    protected function addFilterSelect(string $name, array $options): void
    {
        $hasExplicit = \array_key_exists('placeholder', $options);
        $emptyOption = $hasExplicit ? $options['placeholder'] : true;
        unset($options['placeholder']);
        // Prevent FormKit from putting the auto key on attr.placeholder (ChoiceType empty option is root placeholder).
        $options['placeholder'] = false;

        $merged = $this->mergeFieldOptions($name, 'choice', $options);

        if (true === $emptyOption) {
            $emptyOption = $this->getBlockPrefix().'.'.$name.'.placeholder';
        }

        if (false !== $emptyOption) {
            $merged['placeholder'] = $emptyOption;
            if (isset($merged['attr']) && \is_array($merged['attr'])) {
                unset($merged['attr']['placeholder']);
            }
        }

        $this->boundBuilder()->add($name, ChoiceType::class, $merged);
    }

    /**
     * Shared page + project select for dashboard GET filters.
     *
     * Project empty option uses auto catalogue key {@code {prefix}.project.placeholder}.
     *
     * @param array<string, string> $projectChoices
     */
    protected function addDashboardPageAndProject(
        TranslatorInterface $translator,
        array $projectChoices,
        string $projectAriaLabelKey,
    ): void {
        $this->addHiddenFilterField('page');
        $this->addFilterSelect('project', [
            'choices' => $projectChoices,
            'choice_translation_domain' => false,
            'attr' => [
                'class' => 'input',
                'aria-label' => $translator->trans($projectAriaLabelKey, [], 'form'),
            ],
            'row_attr' => ['class' => 'dashboard-filters__field'],
        ]);
    }

    /**
     * Shared per-page select ({@code dashboard_filter.per_page.*} in {@code form} catalogue).
     */
    protected function addDashboardPerPage(TranslatorInterface $translator): void
    {
        $perPageChoices = [];
        foreach (DashboardProjectFilterFields::PER_PAGE_SIZES as $size) {
            $perPageChoices[$translator->trans(
                'dashboard_filter.per_page.option',
                ['%count%' => (string) $size],
                'form',
            )] = $size;
        }

        $this->addFilterSelect('per_page', [
            'required' => true,
            'choices' => $perPageChoices,
            'choice_translation_domain' => false,
            'placeholder' => false,
            // Help uses FormKit auto_help → {block_prefix}.per_page.help (catalogue per filter).
            'attr' => [
                'class' => 'input',
                'aria-label' => $translator->trans('dashboard_filter.per_page.aria', [], 'form'),
            ],
            'row_attr' => ['class' => 'dashboard-filters__field'],
        ]);
    }

    /**
     * @param array<string, string> $projectChoices
     */
    protected function addDashboardPageProjectAndPerPage(
        TranslatorInterface $translator,
        array $projectChoices,
        string $projectAriaLabelKey,
    ): void {
        $this->addDashboardPageAndProject(
            $translator,
            $projectChoices,
            $projectAriaLabelKey,
        );
        $this->addDashboardPerPage($translator);
    }
}
