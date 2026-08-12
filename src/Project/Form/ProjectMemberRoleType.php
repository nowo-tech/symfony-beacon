<?php

declare(strict_types=1);

namespace App\Project\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Changes a direct project member role.
 */
final class ProjectMemberRoleType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addChoiceField('role', [
                'required' => true,
                'label' => false,
                'choices' => $options['role_choices'],
                'choice_translation_domain' => 'messages',
                'placeholder' => false,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_member_role',
            'translation_domain' => 'messages',
            'role_choices' => [],
        ]);
        $resolver->setAllowedTypes('role_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
