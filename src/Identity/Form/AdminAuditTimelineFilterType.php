<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\AbstractGetFilterType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shared admin audit timeline filters (FormKit {@code filter}: no labels).
 *
 * Action choice labels stay in {@code messages} ({@code users.activity.action.*}).
 * Empty option / help use {@code form} → {@code admin_audit_timeline_filter.*}.
 * Visible field captions are Twig chrome ({@code admin_projects.audit_filter_*}) — not FormKit labels.
 */
final class AdminAuditTimelineFilterType extends AbstractGetFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $actionChoices */
        $actionChoices = $options['action_choices'];

        $this->withBuilder($builder, function () use ($actionChoices): void {
            $this->addFilterSelect('action', [
                'choices' => $actionChoices,
                'choice_translation_domain' => 'messages',
                'attr' => ['class' => 'input w-full'],
                'row_attr' => ['class' => 'sm:col-span-2 flex flex-col gap-2'],
            ]);
            $this->addTextField('from', [
                'attr' => [
                    'class' => 'input w-full',
                    'type' => 'date',
                ],
            ]);
            $this->addTextField('to', [
                'attr' => [
                    'class' => 'input w-full',
                    'type' => 'date',
                ],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'action_choices' => [],
        ]);
        $resolver->setAllowedTypes('action_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_audit_timeline_filter';
    }
}
