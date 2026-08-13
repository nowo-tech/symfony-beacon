<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\AbstractGetFilterType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard mentions inbox filters (FormKit {@code filter}).
 */
final class DashboardMentionsFilterType extends AbstractGetFilterType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];

        $this->withBuilder($builder, function () use ($projectChoices): void {
            $this->addDashboardPageAndProject(
                $this->translator,
                $projectChoices,
                'dashboard_mentions_filter.project.aria',
            );
            $this->addNamedField('unread', 'checkbox', [
                'label' => false,
                'placeholder' => false,
                'help' => false,
            ]);
            $this->addDashboardPerPage($this->translator);
        });
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

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'dashboard_mentions_filter';
    }
}
