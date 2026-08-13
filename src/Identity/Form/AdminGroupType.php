<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\UserGroup;
use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Admin create/edit user group (name + optional description).
 */
final class AdminGroupType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('name', [
                'label' => 'groups.name_label',
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->addTextareaField('description', [
                'label' => 'groups.description_label',
                'required' => false,
                'constraints' => [new Length(max: 2000)],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserGroup::class,
            'csrf_protection' => true,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_group';
    }
}
