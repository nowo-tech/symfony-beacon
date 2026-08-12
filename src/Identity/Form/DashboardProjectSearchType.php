<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\AbstractGetFilterType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Dashboard project search box.
 */
final class DashboardProjectSearchType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('q', SearchType::class, [
            'label' => false,
            'required' => false,
            'attr' => [
                'class' => 'input min-w-0 flex-1',
                'autocomplete' => 'off',
                'placeholder' => 'projects.search_placeholder',
                'aria-label' => 'projects.search_placeholder',
            ],
        ]);
    }
}
