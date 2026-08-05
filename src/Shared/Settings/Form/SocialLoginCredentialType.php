<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Admin form for AuthKit {@see \Nowo\AuthKitBundle\Entity\SocialLoginCredential} rows.
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
                'label' => 'social_login_credential.provider.label',
                'help' => 'social_login_credential.provider.help',
                'disabled' => $providerLocked,
                'constraints' => $providerLocked ? [] : [
                    new NotBlank(),
                    new Length(min: 2, max: 64),
                    new Regex(pattern: '/^[a-z][a-z0-9_-]*$/', message: 'social_login_credential.provider.invalid'),
                ],
            ]);
            $this->addTextField('label', [
                'placeholder' => false,
                'label' => 'social_login_credential.label.label',
                'help' => 'social_login_credential.label.help',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 128),
                ],
            ]);
            $this->addTextField('client_id', [
                'placeholder' => false,
                'label' => 'social_login_credential.client_id.label',
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
                    'label' => 'social_login_credential.client_secret.label',
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
                'label' => 'social_login_credential.enabled.label',
                'help' => 'social_login_credential.enabled.help',
                'required' => false,
            ]);
            $this->addCheckboxField('enterprise_sso', [
                'placeholder' => false,
                'label' => 'social_login_credential.enterprise_sso.label',
                'help' => 'social_login_credential.enterprise_sso.help',
                'required' => false,
            ]);
            $this->addUrlField('authorize_url', [
                'placeholder' => false,
                'label' => 'social_login_credential.authorize_url.label',
                'help' => 'social_login_credential.authorize_url.help',
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addUrlField('token_url', [
                'placeholder' => false,
                'label' => 'social_login_credential.token_url.label',
                'help' => 'social_login_credential.token_url.help',
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addUrlField('userinfo_url', [
                'placeholder' => false,
                'label' => 'social_login_credential.userinfo_url.label',
                'help' => 'social_login_credential.userinfo_url.help',
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ]);
            $this->addTextField('scopes', [
                'placeholder' => false,
                'label' => 'social_login_credential.scopes.label',
                'help' => 'social_login_credential.scopes.help',
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
            'is_new' => true,
            'provider_locked' => false,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
        $resolver->setAllowedTypes('provider_locked', 'bool');
    }
}
