<?php

declare(strict_types=1);

namespace App\Identity\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Permission checkbox matrix for an instance role.
 */
final class AdminRolePermissionsType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<int> $permissionIds */
        $permissionIds = $options['permission_ids'];

        $this->withBuilder($builder, function () use ($permissionIds): void {
            foreach ($permissionIds as $permissionId) {
                $this->addCheckboxField('permission_'.$permissionId, [
                    'required' => false,
                    'label' => false,
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'admin_instance_role_permissions',
            'translation_domain' => 'messages',
            'allow_extra_fields' => true,
            'permission_ids' => [],
        ]);
        $resolver->setAllowedTypes('permission_ids', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
