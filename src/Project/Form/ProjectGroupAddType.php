<?php

declare(strict_types=1);

namespace App\Project\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Links a user group to a project with a selected access role.
 */
final class ProjectGroupAddType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addChoiceField('group', [
                'required' => true,
                'label' => false,
                'choices' => $options['group_choices'],
                'choice_translation_domain' => false,
                'placeholder' => 'project.groups.select',
                'constraints' => [new NotBlank()],
            ]);
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
            'csrf_token_id' => 'project_group_add',
            'translation_domain' => 'messages',
            'group_choices' => [],
            'role_choices' => [],
        ]);
        $resolver->setAllowedTypes('group_choices', 'array');
        $resolver->setAllowedTypes('role_choices', 'array');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
