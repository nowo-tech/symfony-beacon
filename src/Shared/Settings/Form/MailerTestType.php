<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Sends a sample mail to a chosen recipient (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code mailer_test.*}.
 */
final class MailerTestType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addEmailField('to', [
                'help' => false,
                'required' => false,
                'constraints' => [new Email(), new Length(max: 180)],
                'attr' => [
                    'id' => 'mailer-sample-to',
                    'autocomplete' => 'email',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'mailer_sample',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'mailer_test';
    }
}
