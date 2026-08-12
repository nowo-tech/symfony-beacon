<?php

declare(strict_types=1);

namespace App\Project\Form;

use Override;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Adds a direct project member by email with a selected role.
 */
final class ProjectMemberAddType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addEmailField('email', [
                'required' => true,
                'label' => false,
                'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
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
            'csrf_token_id' => 'project_member_add',
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
