<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Settings\Entity\InstanceSettings;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Instance Mailer settings (DSN stored encrypted; blank keeps current value).
 *
 * @extends AbstractType<InstanceSettings>
 */
final class InstanceMailerSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainMailerDsn', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'instance_mailer.mailer_dsn.label',
                'help' => 'instance_mailer.mailer_dsn.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'smtp://user:pass@mail.example:587',
                ],
                'constraints' => [
                    new Length(max: 2048),
                ],
            ])
            ->add('clearMailerDsn', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'instance_mailer.clear_dsn.label',
                'help' => 'instance_mailer.clear_dsn.help',
            ])
            ->add('mailerFrom', EmailType::class, [
                'required' => false,
                'label' => 'instance_mailer.mailer_from.label',
                'help' => 'instance_mailer.mailer_from.help',
                'attr' => [
                    'placeholder' => 'beacon@example.com',
                ],
                'constraints' => [
                    new Email(),
                    new Length(max: 180),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceSettings::class,
        ]);
    }
}
