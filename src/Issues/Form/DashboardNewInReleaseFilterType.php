<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\AbstractGetFilterType;
use App\Shared\Form\DashboardProjectFilterFields;
use Override;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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

        DashboardProjectFilterFields::addPageAndProject(
            $builder,
            $this->translator,
            $projectChoices,
            'new_in_release.filter.any_project',
            'new_in_release.filter.project',
        );
        $builder->add('release', ChoiceType::class, [
            'label' => false,
            'required' => false,
            'choices' => $releaseChoices,
            'choice_translation_domain' => false,
            'placeholder' => $this->translator->trans('new_in_release.filter.any_release'),
            'attr' => [
                'class' => 'input',
                'aria-label' => 'new_in_release.filter.release',
            ],
        ]);
        DashboardProjectFilterFields::addPerPage($builder, $this->translator);
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
