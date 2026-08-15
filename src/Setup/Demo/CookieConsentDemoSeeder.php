<?php

declare(strict_types=1);

namespace App\Setup\Demo;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;

/**
 * Idempotent default cookie-consent profile + inventory for platform seed.
 *
 * Aligns with {@see config/packages/nowo_cookie_consent.yaml} (categories, auth-only
 * auto-show, Beacon legal privacy route, first-party cookie inventory).
 */
final readonly class CookieConsentDemoSeeder
{
    use StrictFixtureReader;

    private const string FIXTURE_FILE = 'cookie_consent.default.json';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CookieConsentConfigRepository $configRepository,
        private CookieDefinitionRepository $definitionRepository,
        private DemoFixtureLoader $fixtureLoader,
    ) {
    }

    /**
     * @return bool true when any config, translation, or cookie definition changed
     */
    public function seedIfEmpty(): bool
    {
        $fixture = $this->fixtureLoader->load(self::FIXTURE_FILE);
        $configData = $this->requireArray($fixture, 'config', 'root');
        $settings = $this->requireArray($configData, 'settings', 'config');
        $modalCopy = $this->requireArray($fixture, 'modalCopy', 'root');
        $cookies = $this->requireList($fixture, 'cookies', 'root');

        $changed = false;
        $config = $this->configRepository->findDefaultEnabled();

        if (!$config instanceof CookieConsentConfig) {
            $config = new CookieConsentConfig()
                ->setName($this->requireString($configData, 'name', 'config'))
                ->setDefault($this->requireBool($configData, 'default', 'config'))
                ->setEnabled($this->requireBool($configData, 'enabled', 'config'));
            $this->entityManager->persist($config);
            $this->entityManager->flush();
            $changed = true;
        }

        $changed = $this->applyBeaconProfileSettings($config, $settings) || $changed;
        $changed = $this->seedTranslations($config, $modalCopy, $this->requireString($settings, 'privacyRoute', 'config.settings')) || $changed;

        if ($changed) {
            $this->entityManager->flush();
        }

        $changed = $this->seedCookieDefinitions($config, $cookies) || $changed;

        if ($changed) {
            $this->entityManager->flush();
        }

        return $changed;
    }

    /**
     * @param array<mixed> $settings
     */
    private function applyBeaconProfileSettings(CookieConsentConfig $config, array $settings): bool
    {
        $changed = false;

        $wanted = [
            'disablePageInteraction' => $this->requireBool($settings, 'disablePageInteraction', 'config.settings'),
            'colorTheme' => $this->requireString($settings, 'colorTheme', 'config.settings'),
            'darkModeEnabled' => $this->requireBool($settings, 'darkModeEnabled', 'config.settings'),
            'preferencesBubbleEnabled' => $this->requireBool($settings, 'preferencesBubbleEnabled', 'config.settings'),
            'preferencesBubblePosition' => $this->requireString($settings, 'preferencesBubblePosition', 'config.settings'),
            'preferencesBubbleIcon' => $this->requireString($settings, 'preferencesBubbleIcon', 'config.settings'),
            'preferencesBubbleBorderColor' => $this->requireString($settings, 'preferencesBubbleBorderColor', 'config.settings'),
            'consentModalLayout' => $this->requireString($settings, 'consentModalLayout', 'config.settings'),
            'consentModalVariant' => $this->requireString($settings, 'consentModalVariant', 'config.settings'),
            'consentModalPositionY' => $this->requireString($settings, 'consentModalPositionY', 'config.settings'),
            'consentModalPositionX' => $this->requireString($settings, 'consentModalPositionX', 'config.settings'),
            'consentModalEqualWeightButtons' => $this->requireBool($settings, 'consentModalEqualWeightButtons', 'config.settings'),
            'preferencesModalLayout' => $this->requireString($settings, 'preferencesModalLayout', 'config.settings'),
            'preferencesModalVariant' => $this->requireString($settings, 'preferencesModalVariant', 'config.settings'),
            'preferencesModalPositionY' => $this->requireString($settings, 'preferencesModalPositionY', 'config.settings'),
            'preferencesModalPositionX' => $this->requireString($settings, 'preferencesModalPositionX', 'config.settings'),
            'preferencesModalEqualWeightButtons' => $this->requireBool($settings, 'preferencesModalEqualWeightButtons', 'config.settings'),
            'autoShow' => $this->requireBool($settings, 'autoShow', 'config.settings'),
            'autoShowRouteMode' => $this->requireString($settings, 'autoShowRouteMode', 'config.settings'),
            'granularCookieSelection' => $this->requireBool($settings, 'granularCookieSelection', 'config.settings'),
            'hideFromBots' => $this->requireBool($settings, 'hideFromBots', 'config.settings'),
        ];

        if ($config->isDisablePageInteraction() !== $wanted['disablePageInteraction']) {
            $config->setDisablePageInteraction($wanted['disablePageInteraction']);
            $changed = true;
        }
        if ($config->getColorTheme() !== $wanted['colorTheme']) {
            $config->setColorTheme($wanted['colorTheme']);
            $changed = true;
        }
        if ($config->isDarkModeEnabled() !== $wanted['darkModeEnabled']) {
            $config->setDarkModeEnabled($wanted['darkModeEnabled']);
            $changed = true;
        }
        if ($config->isPreferencesBubbleEnabled() !== $wanted['preferencesBubbleEnabled']) {
            $config->setPreferencesBubbleEnabled($wanted['preferencesBubbleEnabled']);
            $changed = true;
        }
        if ($config->getPreferencesBubblePosition() !== $wanted['preferencesBubblePosition']) {
            $config->setPreferencesBubblePosition($wanted['preferencesBubblePosition']);
            $changed = true;
        }
        if ($config->getPreferencesBubbleIcon() !== $wanted['preferencesBubbleIcon']) {
            $config->setPreferencesBubbleIcon($wanted['preferencesBubbleIcon']);
            $changed = true;
        }
        if ($config->getPreferencesBubbleBorderColor() !== $wanted['preferencesBubbleBorderColor']) {
            $config->setPreferencesBubbleBorderColor($wanted['preferencesBubbleBorderColor']);
            $changed = true;
        }
        if ($config->getConsentModalLayout() !== $wanted['consentModalLayout']) {
            $config->setConsentModalLayout($wanted['consentModalLayout']);
            $changed = true;
        }
        if ($config->getConsentModalVariant() !== $wanted['consentModalVariant']) {
            $config->setConsentModalVariant($wanted['consentModalVariant']);
            $changed = true;
        }
        if ($config->getConsentModalPositionY() !== $wanted['consentModalPositionY']) {
            $config->setConsentModalPositionY($wanted['consentModalPositionY']);
            $changed = true;
        }
        if ($config->getConsentModalPositionX() !== $wanted['consentModalPositionX']) {
            $config->setConsentModalPositionX($wanted['consentModalPositionX']);
            $changed = true;
        }
        if ($config->isConsentModalEqualWeightButtons() !== $wanted['consentModalEqualWeightButtons']) {
            $config->setConsentModalEqualWeightButtons($wanted['consentModalEqualWeightButtons']);
            $changed = true;
        }
        if ($config->getPreferencesModalLayout() !== $wanted['preferencesModalLayout']) {
            $config->setPreferencesModalLayout($wanted['preferencesModalLayout']);
            $changed = true;
        }
        if ($config->getPreferencesModalVariant() !== $wanted['preferencesModalVariant']) {
            $config->setPreferencesModalVariant($wanted['preferencesModalVariant']);
            $changed = true;
        }
        if ($config->getPreferencesModalPositionY() !== $wanted['preferencesModalPositionY']) {
            $config->setPreferencesModalPositionY($wanted['preferencesModalPositionY']);
            $changed = true;
        }
        if ($config->getPreferencesModalPositionX() !== $wanted['preferencesModalPositionX']) {
            $config->setPreferencesModalPositionX($wanted['preferencesModalPositionX']);
            $changed = true;
        }
        if ($config->isPreferencesModalEqualWeightButtons() !== $wanted['preferencesModalEqualWeightButtons']) {
            $config->setPreferencesModalEqualWeightButtons($wanted['preferencesModalEqualWeightButtons']);
            $changed = true;
        }
        if ($config->isAutoShow() !== $wanted['autoShow']) {
            $config->setAutoShow($wanted['autoShow']);
            $changed = true;
        }
        if ($config->getAutoShowRouteMode() !== $wanted['autoShowRouteMode']) {
            $config->setAutoShowRouteMode($wanted['autoShowRouteMode']);
            $changed = true;
        }

        $routes = $this->requireStringList($settings, 'autoShowRoutes', 'config.settings');
        if ($config->getAutoShowRoutes() !== $routes) {
            $config->setAutoShowRoutes($routes);
            $changed = true;
        }

        if ($config->isGranularCookieSelection() !== $wanted['granularCookieSelection']) {
            $config->setGranularCookieSelection($wanted['granularCookieSelection']);
            $changed = true;
        }
        if ($config->isHideFromBots() !== $wanted['hideFromBots']) {
            $config->setHideFromBots($wanted['hideFromBots']);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param array<mixed> $modalCopy
     */
    private function seedTranslations(CookieConsentConfig $config, array $modalCopy, string $privacyRoute): bool
    {
        $changed = false;

        foreach ($modalCopy as $locale => $copy) {
            if (!\is_string($locale) || !\is_array($copy)) {
                throw new InvalidArgumentException(\sprintf('Fixture "%s" expects modalCopy to be a map of objects.', self::FIXTURE_FILE));
            }

            $translation = $config->findTranslation($locale);
            if (!$translation instanceof CookieConsentConfigTranslation) {
                $translation = new CookieConsentConfigTranslation()->setLocale($locale);
                $config->addTranslation($translation);
                $this->entityManager->persist($translation);
                $changed = true;
            }

            $before = [
                $translation->getConsentModalTitle(),
                $translation->getConsentModalDescription(),
                $translation->getConsentModalFooter(),
                $translation->getConsentModalAcceptAllBtn(),
                $translation->getConsentModalAcceptNecessaryBtn(),
                $translation->getConsentModalShowPreferencesBtn(),
                $translation->getPreferencesModalTitle(),
                $translation->getPreferencesModalSavePreferencesBtn(),
                $translation->getPrivacyRoute(),
            ];

            $translation
                ->setConsentModalTitle($this->requireString($copy, 'title', \sprintf('modalCopy.%s', $locale)))
                ->setConsentModalDescription($this->requireString($copy, 'intro', \sprintf('modalCopy.%s', $locale)))
                ->setConsentModalFooter($this->requireString($copy, 'footer', \sprintf('modalCopy.%s', $locale)))
                ->setConsentModalAcceptAllBtn($this->requireString($copy, 'acceptAll', \sprintf('modalCopy.%s', $locale)))
                ->setConsentModalAcceptNecessaryBtn($this->requireString($copy, 'acceptNecessary', \sprintf('modalCopy.%s', $locale)))
                ->setConsentModalShowPreferencesBtn($this->requireString($copy, 'showPreferences', \sprintf('modalCopy.%s', $locale)))
                ->setPreferencesModalTitle($this->requireString($copy, 'preferencesTitle', \sprintf('modalCopy.%s', $locale)))
                ->setPreferencesModalSavePreferencesBtn($this->requireString($copy, 'save', \sprintf('modalCopy.%s', $locale)))
                ->setPreferencesModalAcceptAllBtn($this->requireString($copy, 'acceptAll', \sprintf('modalCopy.%s', $locale)))
                ->setPreferencesModalAcceptNecessaryBtn($this->requireString($copy, 'acceptNecessary', \sprintf('modalCopy.%s', $locale)))
                ->setPrivacyRoute($privacyRoute);

            $after = [
                $translation->getConsentModalTitle(),
                $translation->getConsentModalDescription(),
                $translation->getConsentModalFooter(),
                $translation->getConsentModalAcceptAllBtn(),
                $translation->getConsentModalAcceptNecessaryBtn(),
                $translation->getConsentModalShowPreferencesBtn(),
                $translation->getPreferencesModalTitle(),
                $translation->getPreferencesModalSavePreferencesBtn(),
                $translation->getPrivacyRoute(),
            ];

            if ($before !== $after) {
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @param list<mixed> $cookies
     */
    private function seedCookieDefinitions(CookieConsentConfig $config, array $cookies): bool
    {
        $changed = $this->renameLegacyConsentCookies($config);

        $existing = [];
        foreach ($this->definitionRepository->findByConfigOrdered($config) as $definition) {
            $existing[$definition->getName()] = $definition;
        }

        $wantedNames = [];
        foreach ($cookies as $index => $row) {
            if (!\is_array($row)) {
                throw new InvalidArgumentException(\sprintf('Fixture "%s" has a non-object cookie definition at index %d.', self::FIXTURE_FILE, $index));
            }

            $context = \sprintf('cookies[%d]', $index);
            $name = $this->resolveCookieName($row, $context);
            $wantedNames[$name] = true;
            $definition = $existing[$name] ?? null;
            if (!$definition instanceof CookieDefinition) {
                $definition = new CookieDefinition()
                    ->setConfig($config)
                    ->setName($name);
                $config->addCookieDefinition($definition);
                $this->entityManager->persist($definition);
                $changed = true;
            }

            $before = [
                $definition->getDuration(),
                $definition->getCategory(),
                $definition->getType(),
                $definition->getSortOrder(),
                $definition->isAllowedByDefault(),
            ];

            $definition
                ->setDuration($this->requireString($row, 'duration', $context))
                ->setCategory($this->requireString($row, 'category', $context))
                ->setType($this->requireString($row, 'type', $context))
                ->setSortOrder($this->requireInt($row, 'sortOrder', $context))
                ->setAllowedByDefault($this->requireBool($row, 'allowedByDefault', $context));

            if ($before !== [
                $definition->getDuration(),
                $definition->getCategory(),
                $definition->getType(),
                $definition->getSortOrder(),
                $definition->isAllowedByDefault(),
            ]) {
                $changed = true;
            }

            foreach ($this->requireLocalizedDefinitionTranslations($row, 'translations', $context) as $locale => $copy) {
                $translation = $definition->findTranslation($locale);
                if (!$translation instanceof CookieDefinitionTranslation) {
                    $translation = new CookieDefinitionTranslation()->setLocale($locale);
                    $definition->addTranslation($translation);
                    $this->entityManager->persist($translation);
                    $changed = true;
                }

                if ($translation->getProvider() !== $copy['provider'] || $translation->getPurpose() !== $copy['purpose']) {
                    $translation
                        ->setProvider($copy['provider'])
                        ->setPurpose($copy['purpose']);
                    $changed = true;
                }
            }
        }

        foreach ($existing as $name => $definition) {
            if (isset($wantedNames[$name])) {
                continue;
            }
            $this->entityManager->remove($definition);
            $changed = true;
        }

        return $changed;
    }

    /**
     * CookieConsentBundle ≥1.1 renamed consent cookies to Cookie_Consent / Cookie_Consent_Key.
     */
    private function renameLegacyConsentCookies(CookieConsentConfig $config): bool
    {
        $map = [
            'CookieConsent' => 'Cookie_Consent',
            'CookieConsentKey' => 'Cookie_Consent_Key',
        ];
        $changed = false;
        foreach ($this->definitionRepository->findByConfigOrdered($config) as $definition) {
            $newName = $map[$definition->getName()] ?? null;
            if (null === $newName) {
                continue;
            }
            $definition->setName($newName);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param array<mixed> $row
     */
    private function resolveCookieName(array $row, string $context): string
    {
        $literalName = $row['name'] ?? null;
        if (\is_string($literalName)) {
            return $literalName;
        }

        $enumName = $row['nameEnum'] ?? null;
        if (\is_string($enumName)) {
            $constant = CookieNameEnum::class.'::'.$enumName;
            if (!\defined($constant)) {
                throw new InvalidArgumentException(\sprintf('Fixture "%s" references unknown CookieNameEnum constant "%s" at %s.', self::FIXTURE_FILE, $enumName, $context));
            }

            $resolved = \constant($constant);
            if (!\is_string($resolved)) {
                throw new InvalidArgumentException(\sprintf('Fixture "%s" constant "%s" did not resolve to a string at %s.', self::FIXTURE_FILE, $enumName, $context));
            }

            return $resolved;
        }

        $categoryName = $row['nameCategory'] ?? null;
        if (\is_string($categoryName)) {
            return CookieNameEnum::getCookieCategoryName($categoryName);
        }

        throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s to define name, nameEnum, or nameCategory.', self::FIXTURE_FILE, $context));
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<string, array{provider: string, purpose: string}>
     */
    private function requireLocalizedDefinitionTranslations(array $row, string $key, string $context): array
    {
        $value = $this->requireArray($row, $key, $context);
        $translations = [];

        foreach ($value as $locale => $copy) {
            if (!\is_string($locale) || !\is_array($copy)) {
                throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be a map of translation objects.', self::FIXTURE_FILE, $context, $key));
            }

            $translations[$locale] = [
                'provider' => $this->requireString($copy, 'provider', \sprintf('%s.%s.%s', $context, $key, $locale)),
                'purpose' => $this->requireString($copy, 'purpose', \sprintf('%s.%s.%s', $context, $key, $locale)),
            ];
        }

        return $translations;
    }
}
