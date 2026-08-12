<?php

declare(strict_types=1);

namespace App\Project\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Transfers project ownership to another direct member after typed confirmation.
 */
final class ProjectTransferOwnershipType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addChoiceField('user', [
                'required' => true,
                'label' => false,
                'choices' => $options['user_choices'],
                'choice_translation_domain' => false,
                'placeholder' => 'project.danger.transfer_member_placeholder',
                'constraints' => [new NotBlank()],
            ]);
            $this->addTextField('confirmation', [
                'required' => true,
                'label' => false,
                'constraints' => [new NotBlank()],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_transfer_ownership',
            'translation_domain' => 'messages',
            'user_choices' => [],
        ]);
        $resolver->setAllowedTypes('user_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
