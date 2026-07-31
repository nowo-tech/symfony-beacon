<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Admin form for AuthKit {@see \Nowo\AuthKitBundle\Entity\SocialLoginCredential} rows.
 *
 * @extends AbstractType<array{
 *     provider: string,
 *     label: string,
 *     client_id: string,
 *     client_secret: string,
 *     enabled: bool,
 *     authorize_url: string,
 *     token_url: string,
 *     userinfo_url: string,
 *     scopes: string
 * }>
 */
final class SocialLoginCredentialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = (bool) $options['is_new'];
        $providerLocked = (bool) $options['provider_locked'];

        $builder
            ->add('provider', TextType::class, [
                'label' => 'social_login_credential.provider.label',
                'help' => 'social_login_credential.provider.help',
                'disabled' => $providerLocked,
                'constraints' => $providerLocked ? [] : [
                    new NotBlank(),
                    new Length(min: 2, max: 64),
                    new Regex(pattern: '/^[a-z][a-z0-9_-]*$/', message: 'social_login_credential.provider.invalid'),
                ],
            ])
            ->add('label', TextType::class, [
                'label' => 'social_login_credential.label.label',
                'help' => 'social_login_credential.label.help',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 128),
                ],
            ])
            ->add('client_id', TextType::class, [
                'label' => 'social_login_credential.client_id.label',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('client_secret', PasswordType::class, [
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
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'social_login_credential.enabled.label',
                'help' => 'social_login_credential.enabled.help',
                'required' => false,
            ])
            ->add('authorize_url', UrlType::class, [
                'label' => 'social_login_credential.authorize_url.label',
                'help' => 'social_login_credential.authorize_url.help',
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ])
            ->add('token_url', UrlType::class, [
                'label' => 'social_login_credential.token_url.label',
                'help' => 'social_login_credential.token_url.help',
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ])
            ->add('userinfo_url', UrlType::class, [
                'label' => 'social_login_credential.userinfo_url.label',
                'help' => 'social_login_credential.userinfo_url.help',
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Length(max: 512)],
            ])
            ->add('scopes', TextType::class, [
                'label' => 'social_login_credential.scopes.label',
                'help' => 'social_login_credential.scopes.help',
                'required' => false,
                'constraints' => [new Length(max: 512)],
            ]);
    }

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
