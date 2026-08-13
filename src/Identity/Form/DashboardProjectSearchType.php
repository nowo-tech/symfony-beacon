<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\AbstractGetFilterType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Dashboard project search box (FormKit {@code filter}).
 *
 * Twig passes {@code attr.placeholder} / {@code aria-label} already translated
 * ({@code projects.search_placeholder|trans}). {@code placeholder: false} skips
 * FormKit auto-placeholder so Twig wins.
 */
final class DashboardProjectSearchType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('q', 'search', [
                'placeholder' => false,
                'help' => false,
                'translation_domain' => false,
                'attr' => [
                    'class' => 'input min-w-0 flex-1',
                    'autocomplete' => 'off',
                ],
            ]);
        });
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'dashboard_project_search';
    }
}
