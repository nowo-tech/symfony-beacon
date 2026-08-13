<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Form\FormKitAbstractType;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Instance Mercure settings (optional live member alerts; URLs + JWT encrypted at rest).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code instance_mercure.*}.
 */
final class InstanceMercureSettingsType extends FormKitAbstractType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly MercureHubUrlGuard $hubUrlGuard,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addCheckboxField('mercureEnabled', [
                'placeholder' => false,
                'required' => false,
            ]);
            $this->addTextField('mercureUrl', [
                'required' => false,
                'constraints' => [
                    new Length(max: 2048),
                    new Callback(function (mixed $value, ExecutionContextInterface $context): void {
                        $this->validateHubUrl(
                            $value,
                            $context,
                            invalidKey: 'instance_mercure.mercure_url.invalid',
                            unsafeKey: 'instance_mercure.mercure_url.unsafe',
                        );
                    }),
                ],
            ]);
            $this->addTextField('mercurePublicUrl', [
                'required' => false,
                'constraints' => [
                    new Length(max: 2048),
                    new Callback(function (mixed $value, ExecutionContextInterface $context): void {
                        $this->validateHubUrl(
                            $value,
                            $context,
                            invalidKey: 'instance_mercure.mercure_public_url.invalid',
                            unsafeKey: 'instance_mercure.mercure_public_url.unsafe',
                        );
                    }),
                ],
            ]);
            $this->boundBuilder()->add(
                'plainMercureJwtSecret',
                PasswordType::class,
                $this->mergeFieldOptions('plainMercureJwtSecret', 'password', [
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => [
                        new Length(max: 512),
                        new Callback($this->validatePlainSecret(...)),
                    ],
                ]),
            );
            $this->addCheckboxField('clearMercureJwtSecret', [
                'placeholder' => false,
                'mapped' => false,
                'required' => false,
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceSettings::class,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'instance_mercure';
    }

    public function validatePlainSecret(mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_string($value)) {
            $context->buildViolation('instance_mercure.plain_mercure_jwt_secret.invalid')
                ->setTranslationDomain('form')
                ->addViolation();

            return;
        }
        if (\strlen(trim($value)) < 32) {
            $context->buildViolation('instance_mercure.plain_mercure_jwt_secret.too_short')
                ->setTranslationDomain('form')
                ->addViolation();
        }
    }

    private function validateHubUrl(
        mixed $value,
        ExecutionContextInterface $context,
        string $invalidKey,
        string $unsafeKey,
    ): void {
        if (null === $value || '' === $value) {
            return;
        }

        $result = $this->hubUrlGuard->classifyHttpUrl(\is_string($value) ? $value : null);
        if (MercureHubUrlGuard::RESULT_VALID === $result) {
            return;
        }

        $context->buildViolation(MercureHubUrlGuard::RESULT_UNSAFE === $result ? $unsafeKey : $invalidKey)
            ->setTranslationDomain('form')
            ->addViolation();
    }
}
