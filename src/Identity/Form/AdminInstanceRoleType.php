<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\InstanceRole;
use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Admin create/edit form for an instance RBAC role.
 *
 * Field label / placeholder / help use FormKit convention keys in the {@code form}
 * domain ({@code admin_instance_role.<field>.*} → translations/form.*.yaml).
 */
final class AdminInstanceRoleType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addTextField('name', [
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->addTextField('code', [
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
                'required' => false,
                'constraints' => [new Length(max: 2000)],
            ]);
            $this->addCheckboxField('enabled', [
                'required' => false,
                'placeholder' => false,
            ]);
            if ($options['with_return_route']) {
                $this->addNamedField('_return', 'hidden', [
                    'mapped' => false,
                    'required' => false,
                    'label' => false,
                    'help' => false,
                    'placeholder' => false,
                    'data' => $options['return_route'],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceRole::class,
            'csrf_protection' => true,
            'code_locked' => false,
            'with_return_route' => false,
            'return_route' => 'admin_roles_show',
        ]);
        $resolver->setAllowedTypes('code_locked', 'bool');
        $resolver->setAllowedTypes('with_return_route', 'bool');
        $resolver->setAllowedTypes('return_route', 'string');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_instance_role';
    }
}
