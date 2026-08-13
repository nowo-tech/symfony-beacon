<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
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
                'label' => false, 'help' => false,
                'choices' => $options['role_choices'],
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
