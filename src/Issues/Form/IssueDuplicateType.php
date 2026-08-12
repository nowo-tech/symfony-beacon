<?php

declare(strict_types=1);

namespace App\Issues\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * CSRF-protected issue duplicate / merge form.
 */
final class IssueDuplicateType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('canonical_uuid', 'hidden', [
                'constraints' => [
                    new NotBlank(),
                ],
            ]);
            $this->addCheckboxField('merge_events', [
                'required' => false,
                'label' => 'issues.merge_events_label',
                'help' => 'issues.merge_events_help',
                'attr' => [
                    'class' => 'checkbox',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'issue_duplicate',
        ]);
    }
}
