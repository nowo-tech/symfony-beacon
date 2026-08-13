<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Shared\Form\AbstractGetFilterType;
use App\Shared\Form\DashboardProjectFilterFields;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard alerts filters.
 */
final class DashboardAlertsFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];

        DashboardProjectFilterFields::addPageProjectAndPerPage(
            $builder,
            $this->translator,
            $projectChoices,
            'alerts.filter.any_project',
            'alerts.filter.project',
        );
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
