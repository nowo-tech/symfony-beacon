<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\InstanceRole;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Admin create/edit form for an instance RBAC role.
 */
final class AdminInstanceRoleType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addTextField('name', [
                'label' => 'roles.name_label',
                'help' => 'roles.name_help',
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->addTextField('code', [
                'label' => 'roles.code_label',
                'help' => 'roles.code_help',
                'disabled' => (bool) $options['code_locked'],
                'constraints' => $options['code_locked'] ? [] : [
                    new NotBlank(),
                    new Length(max: 60),
                    new Regex(
                        pattern: '/^(ROLE_)?[A-Z][A-Z0-9_]*$/i',
                        message: 'roles.code_invalid',
                    ),
                ],
            ]);
            $this->addTextareaField('description', [
                'label' => 'roles.description_label',
                'help' => 'roles.description_help',
                'required' => false,
                'constraints' => [new Length(max: 2000)],
            ]);
            $this->addCheckboxField('enabled', [
                'label' => 'roles.enabled_label',
                'required' => false,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceRole::class,
            'csrf_protection' => true,
            'translation_domain' => 'messages',
            'code_locked' => false,
        ]);
        $resolver->setAllowedTypes('code_locked', 'bool');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_instance_role';
    }
}
