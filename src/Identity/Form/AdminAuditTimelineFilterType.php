<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\AbstractGetFilterType;
use Override;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Shared admin audit timeline filters (`action`, `from`, `to`).
 */
final class AdminAuditTimelineFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $actionChoices */
        $actionChoices = $options['action_choices'];

        $builder
            ->add('action', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $actionChoices,
                'choice_translation_domain' => 'messages',
                'placeholder' => $this->translator->trans('admin_projects.audit_filter_all_actions'),
                'attr' => [
                    'class' => 'input w-full',
                ],
            ])
            ->add('from', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input w-full',
                    'type' => 'date',
                ],
            ])
            ->add('to', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input w-full',
                    'type' => 'date',
                ],
            ]);
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
}
