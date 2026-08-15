<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Nowo\FormKitBundle\Form\AbstractGetFilterType as FormKitAbstractGetFilterType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Beacon GET / list filters — FormKit profile {@code filter} via the kit base type.
 *
 * Host-only helpers for dashboard page/project/per-page chrome live here.
 * Generic CSRF-only / GET filter factories live in {@see Nowo\FormKitBundle\Form}.
 */
abstract class AbstractGetFilterType extends FormKitAbstractGetFilterType
{
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
