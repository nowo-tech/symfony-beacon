<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\AbstractGetFilterType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Builds the issue-list deep-link form for release environment comparison.
 */
final class ProjectReleaseEnvironmentCompareType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', HiddenType::class)
            ->add('release', HiddenType::class, [
                'required' => false,
            ])
            ->add('environment', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input w-full',
                    'placeholder' => 'production',
                ],
            ])
            ->add('compare', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input w-full',
                    'placeholder' => 'staging',
                ],
            ]);
    }
}
