<?php

declare(strict_types=1);

namespace App\Project\Form;

use Override;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Changes a linked group role for a project.
 */
final class ProjectGroupRoleType extends FormKitAbstractType
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
            'csrf_token_id' => 'project_group_role',
            'translation_domain' => 'messages',
            'role_choices' => [],
        ]);
        $resolver->setAllowedTypes('role_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
