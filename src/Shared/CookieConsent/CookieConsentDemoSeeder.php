<?php

declare(strict_types=1);

namespace App\Shared\CookieConsent;

use Doctrine\ORM\EntityManagerInterface;
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
    /** @var list<string> */
    private const array LOCALES = ['en', 'es', 'de', 'nl', 'fr', 'it', 'pt'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CookieConsentConfigRepository $configRepository,
        private CookieDefinitionRepository $definitionRepository,
    ) {
    }

    /**
     * @return bool true when any config, translation, or cookie definition changed
     */
    public function seedIfEmpty(): bool
    {
        $changed = false;
        $config = $this->configRepository->findDefaultEnabled();

        if (!$config instanceof CookieConsentConfig) {
            $config = new CookieConsentConfig()
                ->setName('Beacon default')
                ->setDefault(true)
                ->setEnabled(true);
            $this->entityManager->persist($config);
            $this->entityManager->flush();
            $changed = true;
        }

        $changed = $this->applyBeaconProfileSettings($config) || $changed;
        $changed = $this->seedTranslations($config) || $changed;

        if ($changed) {
            $this->entityManager->flush();
        }

        $changed = $this->seedCookieDefinitions($config) || $changed;

        if ($changed) {
            $this->entityManager->flush();
        }

        return $changed;
    }

    private function applyBeaconProfileSettings(CookieConsentConfig $config): bool
    {
        $changed = false;

        $wanted = [
            'disablePageInteraction' => true,
            'colorTheme' => CookieConsentConfig::COLOR_THEMES[0],
            'darkModeEnabled' => false,
            'preferencesBubbleEnabled' => false,
            'preferencesBubblePosition' => CookieConsentConfig::PREFERENCES_BUBBLE_POSITION_BOTTOM_RIGHT,
            'autoShow' => true,
            'autoShowRouteMode' => CookieConsentConfig::AUTO_SHOW_ROUTE_MODE_ONLY,
            'granularCookieSelection' => false,
            'hideFromBots' => true,
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
        if ($config->isAutoShow() !== $wanted['autoShow']) {
            $config->setAutoShow($wanted['autoShow']);
            $changed = true;
        }
        if ($config->getAutoShowRouteMode() !== $wanted['autoShowRouteMode']) {
            $config->setAutoShowRouteMode($wanted['autoShowRouteMode']);
            $changed = true;
        }

        $routes = [
            'nowo_auth_kit_login',
            'nowo_auth_kit_register',
        ];
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

    private function seedTranslations(CookieConsentConfig $config): bool
    {
        $changed = false;

        foreach ($this->modalCopy() as $locale => $copy) {
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
                ->setConsentModalTitle($copy['title'])
                ->setConsentModalDescription($copy['intro'])
                ->setConsentModalFooter($copy['footer'])
                ->setConsentModalAcceptAllBtn($copy['acceptAll'])
                ->setConsentModalAcceptNecessaryBtn($copy['acceptNecessary'])
                ->setConsentModalShowPreferencesBtn($copy['showPreferences'])
                ->setPreferencesModalTitle($copy['preferencesTitle'])
                ->setPreferencesModalSavePreferencesBtn($copy['save'])
                ->setPreferencesModalAcceptAllBtn($copy['acceptAll'])
                ->setPreferencesModalAcceptNecessaryBtn($copy['acceptNecessary'])
                ->setPrivacyRoute('legal_privacy');

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

    private function seedCookieDefinitions(CookieConsentConfig $config): bool
    {
        $changed = $this->renameLegacyConsentCookies($config);

        $existing = [];
        foreach ($this->definitionRepository->findByConfigOrdered($config) as $definition) {
            $existing[$definition->getName()] = $definition;
        }

        $wantedNames = [];
        foreach ($this->cookieInventory() as $row) {
            $wantedNames[$row['name']] = true;
            $definition = $existing[$row['name']] ?? null;
            if (!$definition instanceof CookieDefinition) {
                $definition = new CookieDefinition()
                    ->setConfig($config)
                    ->setName($row['name']);
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
                ->setDuration($row['duration'])
                ->setCategory($row['category'])
                ->setType($row['type'])
                ->setSortOrder($row['sortOrder'])
                ->setAllowedByDefault($row['allowedByDefault']);

            if ($before !== [
                $definition->getDuration(),
                $definition->getCategory(),
                $definition->getType(),
                $definition->getSortOrder(),
                $definition->isAllowedByDefault(),
            ]) {
                $changed = true;
            }

            foreach ($row['translations'] as $locale => $copy) {
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
     * @return array<string, array{
     *     title: string,
     *     intro: string,
     *     footer: string,
     *     acceptAll: string,
     *     acceptNecessary: string,
     *     showPreferences: string,
     *     preferencesTitle: string,
     *     save: string
     * }>
     */
    private function modalCopy(): array
    {
        return [
            'en' => [
                'title' => 'Cookie notice',
                'intro' => 'This service uses cookies and similar technologies to operate securely and to remember your choices. Strictly necessary cookies are required for core functionality (authentication, security, and recording your consent) and cannot be disabled. Optional cookies — including analytics and preference storage — are used only where you give prior consent. You may accept all categories, refuse non-essential cookies, or review each category. You may withdraw or change your consent at any time via Cookie settings. Further information is available in our Privacy policy.',
                'footer' => 'Privacy policy',
                'acceptAll' => 'Accept all cookies',
                'acceptNecessary' => 'Necessary cookies only',
                'showPreferences' => 'Cookie settings',
                'preferencesTitle' => 'Cookie settings',
                'save' => 'Confirm selection',
            ],
            'es' => [
                'title' => 'Aviso de cookies',
                'intro' => 'Este servicio utiliza cookies y tecnologías similares para funcionar de forma segura y recordar sus elecciones. Las cookies estrictamente necesarias son imprescindibles para la funcionalidad esencial (autenticación, seguridad y registro de su consentimiento) y no pueden desactivarse. Las cookies opcionales —incluidas las de analítica y de preferencias— se utilizan únicamente cuando usted otorga su consentimiento previo. Puede aceptar todas las categorías, rechazar las no esenciales o revisar cada categoría. Puede retirar o modificar su consentimiento en cualquier momento mediante Configuración de cookies. Encontrará más información en nuestra Política de privacidad.',
                'footer' => 'Política de privacidad',
                'acceptAll' => 'Aceptar todas las cookies',
                'acceptNecessary' => 'Solo cookies necesarias',
                'showPreferences' => 'Configuración de cookies',
                'preferencesTitle' => 'Configuración de cookies',
                'save' => 'Confirmar selección',
            ],
            'de' => [
                'title' => 'Cookie-Hinweis',
                'intro' => 'Dieser Dienst verwendet Cookies und ähnliche Technologien, um sicher zu funktionieren und Ihre Auswahl zu speichern. Unbedingt erforderliche Cookies sind für Kernfunktionen (Authentifizierung, Sicherheit und Speicherung Ihrer Einwilligung) notwendig und können nicht deaktiviert werden. Optionale Cookies — einschließlich Analyse und Präferenzen — werden nur mit Ihrer vorherigen Einwilligung eingesetzt. Sie können alle Kategorien akzeptieren, nicht erforderliche ablehnen oder jede Kategorie prüfen. Sie können Ihre Einwilligung jederzeit über die Cookie-Einstellungen widerrufen oder ändern. Weitere Informationen finden Sie in unserer Datenschutzerklärung.',
                'footer' => 'Datenschutzerklärung',
                'acceptAll' => 'Alle Cookies akzeptieren',
                'acceptNecessary' => 'Nur notwendige Cookies',
                'showPreferences' => 'Cookie-Einstellungen',
                'preferencesTitle' => 'Cookie-Einstellungen',
                'save' => 'Auswahl bestätigen',
            ],
            'nl' => [
                'title' => 'Cookiemelding',
                'intro' => 'Deze dienst gebruikt cookies en vergelijkbare technologieën om veilig te functioneren en uw keuzes te onthouden. Strikt noodzakelijke cookies zijn vereist voor kernfunctionaliteit (authenticatie, beveiliging en vastlegging van uw toestemming) en kunnen niet worden uitgeschakeld. Optionele cookies — waaronder analyse en voorkeuren — worden alleen gebruikt met uw voorafgaande toestemming. U kunt alle categorieën accepteren, niet-essentiële weigeren of elke categorie beoordelen. U kunt uw toestemming te allen tijde intrekken of wijzigen via Cookie-instellingen. Meer informatie vindt u in ons Privacybeleid.',
                'footer' => 'Privacybeleid',
                'acceptAll' => 'Alle cookies accepteren',
                'acceptNecessary' => 'Alleen noodzakelijke cookies',
                'showPreferences' => 'Cookie-instellingen',
                'preferencesTitle' => 'Cookie-instellingen',
                'save' => 'Selectie bevestigen',
            ],
            'fr' => [
                'title' => 'Avis relatif aux cookies',
                'intro' => 'Ce service utilise des cookies et des technologies similaires afin de fonctionner de manière sécurisée et de mémoriser vos choix. Les cookies strictement nécessaires sont indispensables au fonctionnement essentiel (authentification, sécurité et enregistrement de votre consentement) et ne peuvent pas être désactivés. Les cookies facultatifs — y compris ceux d’analyse et de préférences — ne sont utilisés qu’avec votre consentement préalable. Vous pouvez accepter toutes les catégories, refuser les cookies non essentiels ou examiner chaque catégorie. Vous pouvez retirer ou modifier votre consentement à tout moment via les Paramètres des cookies. Pour plus d’informations, consultez notre Politique de confidentialité.',
                'footer' => 'Politique de confidentialité',
                'acceptAll' => 'Accepter tous les cookies',
                'acceptNecessary' => 'Cookies nécessaires uniquement',
                'showPreferences' => 'Paramètres des cookies',
                'preferencesTitle' => 'Paramètres des cookies',
                'save' => 'Confirmer la sélection',
            ],
            'it' => [
                'title' => 'Informativa sui cookie',
                'intro' => 'Questo servizio utilizza cookie e tecnologie simili per operare in modo sicuro e ricordare le sue scelte. I cookie strettamente necessari sono indispensabili per le funzionalità essenziali (autenticazione, sicurezza e registrazione del consenso) e non possono essere disattivati. I cookie opzionali — inclusi quelli di analisi e di preferenze — sono utilizzati solo previo consenso. Può accettare tutte le categorie, rifiutare i non essenziali o esaminare ciascuna categoria. Può revocare o modificare il consenso in qualsiasi momento tramite Impostazioni cookie. Ulteriori informazioni sono disponibili nella Privacy policy.',
                'footer' => 'Informativa sulla privacy',
                'acceptAll' => 'Accetta tutti i cookie',
                'acceptNecessary' => 'Solo cookie necessari',
                'showPreferences' => 'Impostazioni cookie',
                'preferencesTitle' => 'Impostazioni cookie',
                'save' => 'Conferma selezione',
            ],
            'pt' => [
                'title' => 'Aviso de cookies',
                'intro' => 'Este serviço utiliza cookies e tecnologias semelhantes para funcionar de forma segura e recordar as suas escolhas. Os cookies estritamente necessários são imprescindíveis para a funcionalidade essencial (autenticação, segurança e registo do seu consentimento) e não podem ser desativados. Os cookies opcionais — incluindo análise e preferências — são utilizados apenas com o seu consentimento prévio. Pode aceitar todas as categorias, recusar as não essenciais ou analisar cada categoria. Pode retirar ou alterar o consentimento a qualquer momento através das Definições de cookies. Encontrará mais informações na nossa Política de privacidade.',
                'footer' => 'Política de privacidade',
                'acceptAll' => 'Aceitar todos os cookies',
                'acceptNecessary' => 'Apenas cookies necessários',
                'showPreferences' => 'Definições de cookies',
                'preferencesTitle' => 'Definições de cookies',
                'save' => 'Confirmar seleção',
            ],
        ];
    }

    /**
     * @return list<array{
     *     name: string,
     *     duration: string,
     *     category: string,
     *     type: string,
     *     sortOrder: int,
     *     allowedByDefault: bool,
     *     translations: array<string, array{provider: string, purpose: string}>
     * }>
     */
    private function cookieInventory(): array
    {
        $provider = [
            'en' => 'Beacon (first-party)',
            'es' => 'Beacon (primera parte)',
            'de' => 'Beacon (First-Party)',
            'nl' => 'Beacon (first-party)',
            'fr' => 'Beacon (première partie)',
            'it' => 'Beacon (prima parte)',
            'pt' => 'Beacon (primeira parte)',
        ];

        $cookies = [
            [
                'name' => 'PHPSESSID',
                'duration' => 'Session',
                'category' => 'required',
                'type' => CookieDefinition::TYPE_FIRST_PARTY,
                'sortOrder' => 0,
                'allowedByDefault' => true,
                'purpose' => [
                    'en' => 'Maintains the authenticated HTTP session required to access protected areas of the service.',
                    'es' => 'Mantiene la sesión HTTP autenticada necesaria para acceder a las áreas protegidas del servicio.',
                    'de' => 'Erhält die authentifizierte HTTP-Sitzung aufrecht, die für den Zugriff auf geschützte Bereiche des Dienstes erforderlich ist.',
                    'nl' => 'Handhaaft de geauthenticeerde HTTP-sessie die nodig is om beveiligde delen van de dienst te gebruiken.',
                    'fr' => 'Maintient la session HTTP authentifiée nécessaire pour accéder aux zones protégées du service.',
                    'it' => 'Mantiene la sessione HTTP autenticata necessaria per accedere alle aree protette del servizio.',
                    'pt' => 'Mantém a sessão HTTP autenticada necessária para aceder às áreas protegidas do serviço.',
                ],
            ],
            [
                'name' => 'REMEMBERME',
                'duration' => '7 days',
                'category' => 'required',
                'type' => CookieDefinition::TYPE_FIRST_PARTY,
                'sortOrder' => 10,
                'allowedByDefault' => true,
                'purpose' => [
                    'en' => 'Enables persistent authentication when the user selects “Remember me” at sign-in (secure token; limited lifetime).',
                    'es' => 'Permite la autenticación persistente cuando el usuario selecciona «Recordarme» al iniciar sesión (token seguro; duración limitada).',
                    'de' => 'Ermöglicht dauerhafte Authentifizierung, wenn der Nutzer bei der Anmeldung „Angemeldet bleiben“ wählt (sicheres Token; begrenzte Laufzeit).',
                    'nl' => 'Maakt persistente authenticatie mogelijk wanneer de gebruiker bij het inloggen “Onthoud mij” selecteert (veilig token; beperkte geldigheid).',
                    'fr' => 'Permet une authentification persistante lorsque l’utilisateur sélectionne « Se souvenir de moi » à la connexion (jeton sécurisé ; durée limitée).',
                    'it' => 'Consente l’autenticazione persistente quando l’utente seleziona “Ricordami” all’accesso (token sicuro; durata limitata).',
                    'pt' => 'Permite autenticação persistente quando o utilizador seleciona “Lembrar-me” no início de sessão (token seguro; validade limitada).',
                ],
            ],
            [
                'name' => 'csrf-token_*',
                'duration' => 'Session',
                'category' => 'required',
                'type' => CookieDefinition::TYPE_FIRST_PARTY,
                'sortOrder' => 15,
                'allowedByDefault' => true,
                'purpose' => [
                    'en' => 'Short-lived double-submit CSRF cookie set by Symfony when submitting protected forms (name pattern csrf-token_<token>; __Host- prefix on HTTPS).',
                    'es' => 'Cookie CSRF de doble envío de corta duración que Symfony establece al enviar formularios protegidos (patrón csrf-token_<token>; prefijo __Host- en HTTPS).',
                    'de' => 'Kurzlebiger Double-Submit-CSRF-Cookie, den Symfony beim Absenden geschützter Formulare setzt (Muster csrf-token_<token>; __Host- Präfix bei HTTPS).',
                    'nl' => 'Kortdurende double-submit CSRF-cookie die Symfony zet bij het verzenden van beveiligde formulieren (patroon csrf-token_<token>; __Host- prefix op HTTPS).',
                    'fr' => 'Cookie CSRF double-soumission de courte durée défini par Symfony lors de l’envoi de formulaires protégés (motif csrf-token_<token> ; préfixe __Host- en HTTPS).',
                    'it' => 'Cookie CSRF double-submit di breve durata impostato da Symfony all’invio di form protetti (pattern csrf-token_<token>; prefisso __Host- su HTTPS).',
                    'pt' => 'Cookie CSRF de double-submit de curta duração definido pelo Symfony ao submeter formulários protegidos (padrão csrf-token_<token>; prefixo __Host- em HTTPS).',
                ],
            ],
            [
                'name' => CookieNameEnum::COOKIE_CONSENT_NAME,
                'duration' => '1 year',
                'category' => 'required',
                'type' => CookieDefinition::TYPE_FIRST_PARTY,
                'sortOrder' => 20,
                'allowedByDefault' => true,
                'purpose' => [
                    'en' => 'Records that a cookie-consent decision was made so the banner is not shown again until preferences change or expire.',
                    'es' => 'Registra que se tomó una decisión de consentimiento de cookies para no volver a mostrar el aviso hasta que se modifiquen o caduquen las preferencias.',
                    'de' => 'Speichert, dass eine Cookie-Einwilligungsentscheidung getroffen wurde, damit der Hinweis nicht erneut angezeigt wird, bis Präferenzen geändert werden oder ablaufen.',
                    'nl' => 'Registreert dat een cookietoestemmingskeuze is gemaakt, zodat de melding niet opnieuw wordt getoond tot voorkeuren wijzigen of verlopen.',
                    'fr' => 'Enregistre qu’une décision de consentement aux cookies a été prise afin de ne pas réafficher l’avis tant que les préférences n’ont pas changé ou expiré.',
                    'it' => 'Registra che è stata presa una decisione di consenso ai cookie affinché l’avviso non venga riproposto finché le preferenze non cambiano o scadono.',
                    'pt' => 'Regista que foi tomada uma decisão de consentimento de cookies para não voltar a apresentar o aviso até que as preferências sejam alteradas ou caduquem.',
                ],
            ],
            [
                'name' => CookieNameEnum::COOKIE_CONSENT_KEY_NAME,
                'duration' => '1 year',
                'category' => 'required',
                'type' => CookieDefinition::TYPE_FIRST_PARTY,
                'sortOrder' => 30,
                'allowedByDefault' => true,
                'purpose' => [
                    'en' => 'Anonymous technical key linking the browser consent record to the server-side consent audit log (accountability).',
                    'es' => 'Clave técnica anónima que vincula el registro de consentimiento del navegador con el registro de auditoría en el servidor (responsabilidad).',
                    'de' => 'Anonyme technische Kennung, die den Browser-Einwilligungsdatensatz mit dem serverseitigen Audit-Protokoll verknüpft (Rechenschaftspflicht).',
                    'nl' => 'Anonieme technische sleutel die het browser-toestemmingsrecord koppelt aan het server-side auditlogboek (verantwoording).',
                    'fr' => 'Clé technique anonyme reliant l’enregistrement de consentement du navigateur au journal d’audit côté serveur (responsabilité).',
                    'it' => 'Chiave tecnica anonima che collega il record di consenso del browser al registro di audit lato server (responsabilità).',
                    'pt' => 'Chave técnica anónima que associa o registo de consentimento do navegador ao registo de auditoria no servidor (responsabilização).',
                ],
            ],
            [
                'name' => CookieNameEnum::getCookieCategoryName('analytics'),
                'duration' => '1 year',
                'category' => 'required',
                'type' => CookieDefinition::TYPE_FIRST_PARTY,
                'sortOrder' => 40,
                'allowedByDefault' => true,
                'purpose' => [
                    'en' => 'Stores whether the analytics category was accepted or refused (consent machinery; not an analytics tracker itself).',
                    'es' => 'Almacena si la categoría de analítica fue aceptada o rechazada (mecanismo de consentimiento; no es un rastreador de analítica).',
                    'de' => 'Speichert, ob die Analyse-Kategorie akzeptiert oder abgelehnt wurde (Einwilligungsmechanik; kein Analyse-Tracker).',
                    'nl' => 'Slaat op of de analysecategorie is geaccepteerd of geweigerd (toestemmingsmechanisme; geen analysetracker).',
                    'fr' => 'Enregistre si la catégorie analytique a été acceptée ou refusée (mécanisme de consentement ; pas un outil d’analyse).',
                    'it' => 'Memorizza se la categoria analytics è stata accettata o rifiutata (meccanismo di consenso; non è un tracker analytics).',
                    'pt' => 'Armazena se a categoria de análise foi aceite ou recusada (mecanismo de consentimento; não é um rastreador de análise).',
                ],
            ],
            [
                'name' => CookieNameEnum::getCookieCategoryName('preferences'),
                'duration' => '1 year',
                'category' => 'required',
                'type' => CookieDefinition::TYPE_FIRST_PARTY,
                'sortOrder' => 50,
                'allowedByDefault' => true,
                'purpose' => [
                    'en' => 'Stores whether the preferences category was accepted or refused (consent machinery).',
                    'es' => 'Almacena si la categoría de preferencias fue aceptada o rechazada (mecanismo de consentimiento).',
                    'de' => 'Speichert, ob die Präferenz-Kategorie akzeptiert oder abgelehnt wurde (Einwilligungsmechanik).',
                    'nl' => 'Slaat op of de voorkeurencategorie is geaccepteerd of geweigerd (toestemmingsmechanisme).',
                    'fr' => 'Enregistre si la catégorie préférences a été acceptée ou refusée (mécanisme de consentement).',
                    'it' => 'Memorizza se la categoria preferences è stata accettata o rifiutata (meccanismo di consenso).',
                    'pt' => 'Armazena se a categoria de preferências foi aceite ou recusada (mecanismo de consentimento).',
                ],
            ],
        ];

        $out = [];
        foreach ($cookies as $cookie) {
            $translations = [];
            foreach (self::LOCALES as $locale) {
                $translations[$locale] = [
                    'provider' => $provider[$locale],
                    'purpose' => $cookie['purpose'][$locale],
                ];
            }
            $out[] = [
                'name' => $cookie['name'],
                'duration' => $cookie['duration'],
                'category' => $cookie['category'],
                'type' => $cookie['type'],
                'sortOrder' => $cookie['sortOrder'],
                'allowedByDefault' => $cookie['allowedByDefault'],
                'translations' => $translations,
            ];
        }

        return $out;
    }
}
