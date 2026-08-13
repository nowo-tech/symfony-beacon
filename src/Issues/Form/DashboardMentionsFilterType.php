<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\AbstractGetFilterType;
use App\Shared\Form\DashboardProjectFilterFields;
use Override;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard mentions inbox filters.
 */
final class DashboardMentionsFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];

        DashboardProjectFilterFields::addPageAndProject(
            $builder,
            $this->translator,
            $projectChoices,
            'mentions.filter.any_project',
            'mentions.filter.project',
        );
        $builder->add('unread', CheckboxType::class, [
            'label' => false,
            'required' => false,
            'attr' => [
                'class' => 'checkbox',
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
        ]);
        $resolver->setAllowedTypes('project_choices', 'array');
    }
}
