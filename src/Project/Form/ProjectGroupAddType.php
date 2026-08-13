<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Links a user group to a project (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_group_add.*}.
 * Role choice labels use {@code form} ({@code project.members.role.*}).
 */
final class ProjectGroupAddType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addChoiceWithFormPlaceholder('group', [
                'required' => true,
                'choices' => $options['group_choices'],
                'choice_translation_domain' => false,
                'constraints' => [new NotBlank()],
            ]);
            $this->addChoiceField('role', [
                'help' => false,
                'placeholder' => false,
                'required' => true,
                'choices' => $options['role_choices'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_group_add',
            'group_choices' => [],
            'role_choices' => [],
        ]);
        $resolver->setAllowedTypes('group_choices', 'array');
        $resolver->setAllowedTypes('role_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_group_add';
    }
}
