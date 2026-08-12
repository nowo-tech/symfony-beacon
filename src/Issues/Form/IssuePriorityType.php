<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\Enum\IssuePriority;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * CSRF-protected issue priority update form.
 */
final class IssuePriorityType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach (IssuePriority::cases() as $priority) {
            $choices['issues.priority.'.$priority->value] = $priority->value;
        }
        $priorityValues = array_map(static fn (IssuePriority $priority): string => $priority->value, IssuePriority::cases());

        $this->withBuilder($builder, function () use ($choices, $priorityValues): void {
            $this->addChoiceField('priority', [
                'label' => false,
                'choices' => $choices,
                'choice_translation_domain' => 'messages',
                'required' => true,
                'placeholder' => false,
                'constraints' => [
                    new NotBlank(),
                    new Choice(choices: $priorityValues),
                ],
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'issues.priority_label',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'issue_priority',
        ]);
    }
}
