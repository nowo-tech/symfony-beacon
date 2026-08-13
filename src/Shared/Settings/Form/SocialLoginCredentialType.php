<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Form\FormKitAbstractType;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Admin form for AuthKit {@see \Nowo\AuthKitBundle\Entity\SocialLoginCredential} rows.
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code social_login_credential.*}.
 */
final class SocialLoginCredentialType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = (bool) $options['is_new'];
        $providerLocked = (bool) $options['provider_locked'];

        $this->withBuilder($builder, function () use ($isNew, $providerLocked): void {
            $this->addTextField('provider', [
                'placeholder' => false,
                'disabled' => $providerLocked,
                'constraints' => $providerLocked ? [] : [
                    new NotBlank(),
                    new Length(min: 2, max: 64),
                    new Regex(pattern: '/^[a-z][a-z0-9_-]*$/', message: 'social_login_credential.provider.invalid'),
                ],
            ]);
            $this->addTextField('label', [
                'placeholder' => false,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 128),
                ],
            ]);
            $this->addTextField('client_id', [
                'placeholder' => false,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ]);
            $this->boundBuilder()->add(
                'client_secret',
                PasswordType::class,
                $this->mergeFieldOptions('client_secret', 'password', [
                    'placeholder' => false,
                    'help' => $isNew
                        ? 'social_login_credential.client_secret.help_new'
                        : 'social_login_credential.client_secret.help_edit',
                    'required' => $isNew,
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => array_values(array_filter([
                        $isNew ? new NotBlank() : null,
                        new Length(max: 2048),
                    ])),
                ]),
            );
            $this->addCheckboxField('enabled', [
                'placeholder' => false,
                'required' => false,
            ]);
            $this->addCheckboxField('enterprise_sso', [
                'placeholder' => false,
                'required' => false,
            ]);
            $this->addUrlField('authorize_url', [
                'placeholder' => false,
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addUrlField('token_url', [
                'placeholder' => false,
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addUrlField('userinfo_url', [
                'placeholder' => false,
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addTextField('scopes', [
                'placeholder' => false,
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_new' => true,
            'provider_locked' => false,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
        $resolver->setAllowedTypes('provider_locked', 'bool');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'social_login_credential';
    }
}
