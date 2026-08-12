<?php

declare(strict_types=1);

namespace App\Issues\Form;

use Override;
use App\Shared\Form\AbstractGetFilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard "new in release" filters.
 */
final class DashboardNewInReleaseFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];
        /** @var array<string, string> $releaseChoices */
        $releaseChoices = $options['release_choices'];
        $perPageChoices = [];
        foreach ([10, 25, 50, 100] as $size) {
            $perPageChoices[$this->translator->trans('issues.filter.per_page_option', ['%count%' => (string) $size])] = $size;
        }

        $builder
            ->add('page', HiddenType::class)
            ->add('project', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $projectChoices,
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('new_in_release.filter.any_project'),
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'new_in_release.filter.project',
                ],
            ])
            ->add('release', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $releaseChoices,
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('new_in_release.filter.any_release'),
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'new_in_release.filter.release',
                ],
            ])
            ->add('per_page', ChoiceType::class, [
                'label' => false,
                'required' => true,
                'choices' => $perPageChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'issues.filter.per_page',
                ],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'project_choices' => [],
            'release_choices' => [],
        ]);
        $resolver->setAllowedTypes('project_choices', 'array');
        $resolver->setAllowedTypes('release_choices', 'array');
    }
}
