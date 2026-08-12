<?php

declare(strict_types=1);

namespace App\Analytics\Form;

use App\Shared\Form\AbstractGetFilterType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Per-project analytics filters.
 */
final class AnalyticsFilterType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('from', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                    'type' => 'date',
                ],
            ])
            ->add('to', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                    'type' => 'date',
                ],
            ])
            ->add('period', HiddenType::class)
            ->add('environment', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                    'placeholder' => 'prod',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('release', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                    'placeholder' => '1.2.0',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('level', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                    'placeholder' => 'error',
                    'autocomplete' => 'off',
                ],
            ]);
    }
}
