<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Transfers project ownership (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_transfer_ownership.*}.
 * Confirm-dialog chrome (typed name) stays in Twig; field attrs live on the Type.
 */
final class ProjectTransferOwnershipType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $projectId = (int) $options['project_id'];

        $this->withBuilder($builder, function () use ($options, $projectId): void {
            $this->addChoiceWithFormPlaceholder('user', [
                'required' => true,
                'choices' => $options['user_choices'],
                'choice_translation_domain' => false,
                'placeholder' => 'project.danger.transfer_member_placeholder',
                'constraints' => [new NotBlank()],
                'attr' => [
                    'id' => 'project-transfer-user-'.$projectId,
                    'class' => 'input w-full',
                ],
                'label_attr' => ['class' => 'confirm-dialog__label'],
            ]);
            $this->addTextField('confirmation', [
                'help' => false,
                'placeholder' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new EqualTo((string) $options['confirmation_value']),
                ],
                'attr' => [
                    'id' => 'project-transfer-confirm-'.$projectId,
                    'class' => 'input w-full',
                    'autocomplete' => 'off',
                    'data-confirm-dialog-target' => 'confirmInput',
                ],
                'label_attr' => ['class' => 'confirm-dialog__label'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_transfer_ownership',
            'user_choices' => [],
            'project_id' => 0,
            'confirmation_value' => '',
        ]);
        $resolver->setAllowedTypes('user_choices', 'array');
        $resolver->setAllowedTypes('project_id', 'int');
        $resolver->setAllowedTypes('confirmation_value', 'string');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_transfer_ownership';
    }
}
