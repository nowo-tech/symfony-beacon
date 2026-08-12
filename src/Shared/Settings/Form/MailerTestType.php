<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use Override;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Sends a sample mail to a chosen recipient.
 */
final class MailerTestType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addEmailField('to', [
                'required' => false,
                'label' => false,
                'constraints' => [new Email(), new Length(max: 180)],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'mailer_sample',
            'translation_domain' => 'messages',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
