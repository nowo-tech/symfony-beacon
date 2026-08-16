<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Form;

use App\Identity\Entity\InstancePermission;
use App\Identity\Form\AdminInstancePermissionType;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Form\NotificationDestinationFormType;
use App\Notifications\Formatter\DiscordChannelFormatter;
use App\Notifications\Formatter\HttpChannelFormatter;
use App\Notifications\Formatter\SlackChannelFormatter;
use App\Notifications\Formatter\TeamsChannelFormatter;
use App\Notifications\Formatter\TelegramChannelFormatter;
use App\Notifications\Service\InteractionActionToken;
use App\Notifications\Service\NotificationOutboundFormatter;
use App\Notifications\Service\OutboundUrlGuard;
use App\Project\Entity\Project;
use App\Shared\Appearance\AppearanceSettingsSection;
use App\Shared\Appearance\Form\SiteAppearanceType;
use App\Shared\Rbac\RbacPermissionTranslator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Form\InstanceOpsDefaultsType;
use App\Shared\Settings\OpsDefaultsSection;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ConfiguredFormTypesTest extends TestCase
{
    public function testAdminInstancePermissionTypeHydratesCatalogAndFallbackTranslations(): void
    {
        $translator = $this->translator([
            'permissions.catalog.project_view.name|es' => 'Ver proyecto',
            'permissions.catalog.project_view.description|es' => 'Descripcion del catalogo',
        ]);
        $type = new AdminInstancePermissionType(
            $this->formOptionsMerger(),
            new FormTypeMap(),
            $translator,
            new RbacPermissionTranslator($translator),
        );

        $permission = new InstancePermission()
            ->setKey('project.view')
            ->setName('Fallback name')
            ->setDescription('Fallback description')
            ->setCategory('access');

        $form = $this->formFactory([$type])->create(AdminInstancePermissionType::class, $permission, [
            'enabled_locales' => ['en', 'es'],
            'default_locale' => 'en',
            'csrf_protection' => false,
        ]);

        self::assertSame('Fallback name', $form->get('name_en')->getData());
        self::assertSame('Fallback description', $form->get('description_en')->getData());
        self::assertSame('Ver proyecto', $form->get('name_es')->getData());
        self::assertSame('Descripcion del catalogo', $form->get('description_es')->getData());

        $form->submit([
            'key' => 'project.view',
            'category' => 'access',
            'name_en' => '',
            'description_en' => '',
            'name_es' => 'Permiso traducido',
            'description_es' => 'Descripcion traducida',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame('Permiso traducido', $permission->getName());
        self::assertSame('Descripcion traducida', $permission->getDescription());
        self::assertSame('Permiso traducido', $permission->getNameForLocale('es'));
        self::assertSame('Descripcion traducida', $permission->getDescriptionForLocale('es'));

        $view = $form->createView();
        self::assertSame(['en', 'es'], $view->vars['enabled_locales']);
        self::assertSame('en', $view->vars['default_locale']);
    }

    public function testAdminInstancePermissionTypeIgnoresNullDataOnHydrationAndSubmit(): void
    {
        $translator = $this->translator();
        $type = new AdminInstancePermissionType(
            $this->formOptionsMerger(),
            new FormTypeMap(),
            $translator,
            new RbacPermissionTranslator($translator),
        );

        $form = $this->formFactory([$type])->create(AdminInstancePermissionType::class, null, [
            'enabled_locales' => ['en'],
            'default_locale' => 'en',
            'data_class' => null,
            'csrf_protection' => false,
        ]);
        $form->submit([
            'key' => '',
            'category' => 'access',
            'name_en' => '',
            'description_en' => '',
        ]);

        self::assertTrue($form->isSynchronized());
    }

    public function testNotificationDestinationFormTypeValidatesAndAppliesSecrets(): void
    {
        $settings = InstanceSettings::defaults()->setAllowPrivateUrls(false);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        $type = new NotificationDestinationFormType(
            $this->formOptionsMerger(),
            new FormTypeMap(),
            new NotificationOutboundFormatter(
                new SlackChannelFormatter(),
                new DiscordChannelFormatter(),
                new TeamsChannelFormatter($this->createStub(UrlGeneratorInterface::class), new InteractionActionToken()),
                new TelegramChannelFormatter(),
                new HttpChannelFormatter(),
            ),
            new OutboundUrlGuard(new InstanceOpsDefaults($settingsRepo)),
            $this->translator(),
        );

        $destination = new NotificationDestination()
            ->setProject(new Project()->setName('Beacon')->setSlug('beacon'))
            ->setSigningSecret('keep-me');

        $invalidForm = $this->formFactory([$type])->create(NotificationDestinationFormType::class, $destination);
        $invalidForm->submit([
            'label' => 'Email alert',
            'type' => NotificationDestinationType::Email->value,
            'endpointUrl' => 'not-an-email',
            'signingSecret' => '',
            'clearSigningSecret' => true,
            'enabled' => true,
            'categories' => ['error'],
            'quietHoursEnabled' => true,
            'quietHoursTimezone' => 'Mars/Phobos',
            'quietHoursStart' => '25:00',
            'quietHoursEnd' => '25:00',
            'digestEnabled' => false,
        ]);

        self::assertNull($destination->getSigningSecret());
        self::assertNotEmpty($invalidForm->get('quietHoursTimezone')->getErrors());
        self::assertNotEmpty($invalidForm->get('quietHoursStart')->getErrors());
        self::assertNotEmpty($invalidForm->get('quietHoursEnd')->getErrors());
        self::assertNotEmpty($invalidForm->get('endpointUrl')->getErrors());

        $telegramForm = $this->formFactory([$type])->create(NotificationDestinationFormType::class, new NotificationDestination());
        $telegramForm->submit([
            'label' => 'Telegram alert',
            'type' => NotificationDestinationType::Telegram->value,
            'endpointUrl' => 'bad-telegram-endpoint',
            'signingSecret' => '',
            'clearSigningSecret' => false,
            'enabled' => true,
            'categories' => ['error'],
            'quietHoursEnabled' => false,
            'quietHoursTimezone' => 'UTC',
            'quietHoursStart' => '',
            'quietHoursEnd' => '',
            'digestEnabled' => false,
        ]);
        self::assertNotEmpty($telegramForm->get('endpointUrl')->getErrors());

        $quietHoursForm = $this->formFactory([$type])->create(NotificationDestinationFormType::class, new NotificationDestination());
        $quietHoursForm->submit([
            'label' => 'Digest hours',
            'type' => NotificationDestinationType::Email->value,
            'endpointUrl' => 'ops@example.com',
            'signingSecret' => '',
            'clearSigningSecret' => false,
            'enabled' => true,
            'categories' => ['error'],
            'quietHoursEnabled' => true,
            'quietHoursTimezone' => 'UTC',
            'quietHoursStart' => '',
            'quietHoursEnd' => '',
            'digestEnabled' => false,
        ]);
        self::assertNotEmpty($quietHoursForm->get('quietHoursStart')->getErrors());

        $validTelegramForm = $this->formFactory([$type])->create(NotificationDestinationFormType::class, new NotificationDestination());
        $validTelegramForm->submit([
            'label' => 'Telegram ok',
            'type' => NotificationDestinationType::Telegram->value,
            'endpointUrl' => '123:ABC@-10042',
            'signingSecret' => '',
            'clearSigningSecret' => false,
            'enabled' => true,
            'categories' => ['error'],
            'quietHoursEnabled' => false,
            'quietHoursTimezone' => 'UTC',
            'quietHoursStart' => '',
            'quietHoursEnd' => '',
            'digestEnabled' => false,
        ]);
        self::assertCount(0, $validTelegramForm->get('endpointUrl')->getErrors());

        $httpDestination = new NotificationDestination();
        $httpForm = $this->formFactory([$type])->create(NotificationDestinationFormType::class, $httpDestination);
        $httpForm->submit([
            'label' => 'Webhook',
            'type' => NotificationDestinationType::Http->value,
            'endpointUrl' => 'http://localhost/hook',
            'signingSecret' => '  new-secret  ',
            'clearSigningSecret' => false,
            'enabled' => true,
            'categories' => ['error', 'warning'],
            'quietHoursEnabled' => false,
            'quietHoursTimezone' => 'UTC',
            'quietHoursStart' => '',
            'quietHoursEnd' => '',
            'digestEnabled' => true,
        ]);

        self::assertSame('new-secret', $httpDestination->getSigningSecret());
        self::assertNotEmpty($httpForm->get('endpointUrl')->getErrors());
    }

    public function testInstanceOpsDefaultsTypeIgnoresNonSettingsSubmitData(): void
    {
        $type = new InstanceOpsDefaultsType(
            $this->formOptionsMerger(),
            new FormTypeMap(),
            $this->translator(),
        );

        $form = $this->formFactory([$type])->create(InstanceOpsDefaultsType::class, null, [
            'section' => OpsDefaultsSection::Notifications,
            'data_class' => null,
        ]);
        $form->submit([
            'confirmAllowPrivateUrls' => '',
            'confirmAllowAnonymousResolve' => '',
        ]);

        self::assertTrue($form->isSynchronized());
    }

    public function testSiteAppearanceTypeHandlesThemesAndDefaultColorSubtab(): void
    {
        $type = new SiteAppearanceType($this->formOptionsMerger(), new FormTypeMap());

        $themes = $this->formFactory([$type])->create(SiteAppearanceType::class, null, [
            'section' => AppearanceSettingsSection::Themes,
        ]);
        self::assertSame([], array_keys($themes->all()));

        $colors = $this->formFactory([$type])->create(SiteAppearanceType::class, null, [
            'section' => AppearanceSettingsSection::Colors,
            'subtab' => null,
        ]);
        self::assertTrue($colors->has('accentColor'));
    }

    /** @param list<object> $types */
    private function formFactory(array $types): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([...$types, new PasswordType()], [ChoiceType::class => [new TomSelectChoiceExtension()]]))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }

    private function formOptionsMerger(): FormOptionsMerger
    {
        return new FormOptionsMerger([
            'beacon' => [
                'translation_domain' => 'form',
                'auto_placeholder' => true,
                'auto_help' => true,
                'defaults' => [
                    'attr' => ['class' => 'input'],
                    'row_attr' => ['class' => 'row'],
                ],
                'field_types' => [
                    'text' => [],
                    'textarea' => [],
                    'checkbox' => [],
                    'choice' => [],
                    'password' => [],
                ],
            ],
        ], 'beacon', new ConstraintDefinitionFactory());
    }

    /** @param array<string, string> $translations */
    private function translator(array $translations = []): TranslatorInterface
    {
        return new class($translations) implements TranslatorInterface {
            /** @param array<string, string> $translations */
            public function __construct(private array $translations)
            {
            }

            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                if (null === $id) {
                    return '';
                }

                return $this->translations[$id.'|'.($locale ?? 'en')] ?? $this->translations[$id] ?? $id;
            }

            public function getLocale(): string
            {
                return 'en';
            }

            public function setLocale(string $locale): void
            {
            }
        };
    }
}

final class TomSelectChoiceExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['autocomplete', 'preload', 'tom_select_options']);
    }
}
