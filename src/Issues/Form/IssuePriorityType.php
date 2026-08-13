<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\Enum\IssuePriority;
use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * CSRF-protected issue priority update form (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code issue_priority.*}.
 * Choice labels stay in {@code messages} ({@code issues.priority.*}).
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
                'help' => false,
                'choices' => $choices,
                'choice_translation_domain' => 'messages',
                'required' => true,
                'placeholder' => false,
                'constraints' => [
                    new NotBlank(),
                    new Choice(choices: $priorityValues),
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'issue_priority',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'issue_priority';
    }
}
