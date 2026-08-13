<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Adds an existing user to an admin-managed group by email (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code admin_group_member_add.*}.
 */
final class AdminGroupMemberAddType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addEmailField('email', [
                'help' => false,
                'required' => true,
                'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
                'attr' => [
                    'autocomplete' => 'off',
                ],
                'row_attr' => ['class' => 'grow min-w-56'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'admin_group_member_add',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_group_member_add';
    }
}
