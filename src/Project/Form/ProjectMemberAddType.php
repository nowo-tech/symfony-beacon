<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Adds a direct project member (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_member_add.*}.
 * Role choice labels use {@code form} ({@code project.members.role.*}).
 */
final class ProjectMemberAddType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addEmailField('email', [
                'help' => false,
                'required' => true,
                'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
                'label_attr' => ['class' => 'confirm-dialog__label'],
                'attr' => [
                    'id' => 'member-email',
                    'autocomplete' => 'off',
                ],
            ]);
            $this->addChoiceField('role', [
                'help' => false,
                'placeholder' => false,
                'required' => true,
                'choices' => $options['role_choices'],
                'label_attr' => ['class' => 'confirm-dialog__label'],
                'attr' => ['id' => 'member-add-role'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_member_add',
            'role_choices' => [],
        ]);
        $resolver->setAllowedTypes('role_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_member_add';
    }
}
