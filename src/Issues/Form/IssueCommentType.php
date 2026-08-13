<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Adds a discussion comment to an issue detail page (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code issue_comment.*}.
 */
final class IssueCommentType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextareaField('body', [
                'help' => false,
                'required' => true,
                'constraints' => [new NotBlank(), new Length(max: 5000)],
                'attr' => [
                    'id' => 'issue-comment-body',
                    'rows' => 3,
                    'maxlength' => 5000,
                ],
                'label_attr' => ['class' => 'sr-only'],
                'row_attr' => ['class' => 'mb-2'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'issue_comment',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'issue_comment';
    }
}
