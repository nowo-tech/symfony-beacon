<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use Deprecated;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

/**
 * Seeds sidebar menus for the three app sections (dashboard, preferences, administration).
 */
final readonly class DashboardMenuDemoSeeder
{
    #[Deprecated(message: 'Use AppSection::Dashboard->menuCode()')]
    public const string MAIN_MENU_CODE = 'dashboard';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MenuRepository $menuRepository,
    ) {
    }

    /**
     * @return bool true when any menu or item was created / updated
     */
    public function seedIfEmpty(): bool
    {
        $changed = $this->ensureFlatMenu(
            'dashboard',
            'Dashboard',
            'dashboard-menu-navigation',
            [
                [10, 'Projects', ['en' => 'Projects', 'es' => 'Proyectos', 'de' => 'Projekte', 'nl' => 'Projecten', 'fr' => 'Projets', 'it' => 'Progetti', 'pt' => 'Projetos'], 'dashboard_home', null],
                [20, 'Assignments', ['en' => 'Assignments', 'es' => 'Asignaciones', 'de' => 'Zuweisungen', 'nl' => 'Toewijzingen', 'fr' => 'Affectations', 'it' => 'Assegnazioni', 'pt' => 'Atribuições'], 'dashboard_assignments', null],
                [30, 'Summary', ['en' => 'Summary', 'es' => 'Resumen', 'de' => 'Übersicht', 'nl' => 'Samenvatting', 'fr' => 'Résumé', 'it' => 'Riepilogo', 'pt' => 'Resumo'], 'dashboard_summary', null],
                [40, 'Activity', ['en' => 'Activity', 'es' => 'Actividad', 'de' => 'Aktivität', 'nl' => 'Activiteit', 'fr' => 'Activité', 'it' => 'Attività', 'pt' => 'Atividade'], 'dashboard_activity', null],
                [50, 'Mentions', ['en' => 'Mentions', 'es' => 'Menciones', 'de' => 'Erwähnungen', 'nl' => 'Vermeldingen', 'fr' => 'Mentions', 'it' => 'Menzioni', 'pt' => 'Menções'], 'dashboard_mentions', null],
                [60, 'Alerts', ['en' => 'Alerts', 'es' => 'Alertas', 'de' => 'Warnungen', 'nl' => 'Meldingen', 'fr' => 'Alertes', 'it' => 'Avvisi', 'pt' => 'Alertas'], 'dashboard_alerts', null],
                [70, 'New in release', ['en' => 'New in release', 'es' => 'Nuevo en release', 'de' => 'Neu in Release', 'nl' => 'Nieuw in release', 'fr' => 'Nouveau en release', 'it' => 'Nuovo in release', 'pt' => 'Novo na release'], 'dashboard_new_in_release', null],
            ],
        );

        $changed = $this->ensureFlatMenu(
            'preferences',
            'Preferences',
            'preferences-menu-navigation',
            [
                [10, 'Profile', ['en' => 'Profile', 'es' => 'Perfil', 'de' => 'Profil', 'nl' => 'Profiel', 'fr' => 'Profil', 'it' => 'Profilo', 'pt' => 'Perfil'], 'account_profile', null],
                [20, 'Security', ['en' => 'Security', 'es' => 'Seguridad', 'de' => 'Sicherheit', 'nl' => 'Beveiliging', 'fr' => 'Sécurité', 'it' => 'Sicurezza', 'pt' => 'Segurança'], 'account_security', null],
                [30, 'Display', ['en' => 'Display', 'es' => 'Interfaz', 'de' => 'Anzeige', 'nl' => 'Weergave', 'fr' => 'Affichage', 'it' => 'Visualizzazione', 'pt' => 'Interface'], 'account_display', null],
            ],
        ) || $changed;

        $changed = $this->ensureAdministrationMenu() || $changed;

        // Legacy "main" menu from earlier seeds — keep in sync as dashboard alias or remove extras.
        $legacy = $this->menuRepository->findOneByCodeAndContext('main', null);
        if ($legacy instanceof Menu) {
            foreach ($legacy->getItems()->toArray() as $item) {
                $legacy->removeItem($item);
                $this->entityManager->remove($item);
                $changed = true;
            }
            $this->entityManager->remove($legacy);
            $changed = true;
        }

        if ($changed) {
            $this->entityManager->flush();
        }

        return $changed;
    }

    /**
     * Grouped administration sidebar: sections with collapsible children.
     */
    private function ensureAdministrationMenu(): bool
    {
        /** @var list<array{0: int, 1: string, 2: array<string, string>, 3: list<array{0: int, 1: string, 2: array<string, string>, 3: string, 4: string}>}> $sections */
        $sections = [
            [
                10,
                'Overview',
                [
                    'en' => 'Overview',
                    'es' => 'Visión general',
                    'de' => 'Übersicht',
                    'nl' => 'Overzicht',
                    'fr' => 'Aperçu',
                    'it' => 'Panoramica',
                    'pt' => 'Visão geral',
                ],
                [
                    [10, 'Overview', ['en' => 'Overview', 'es' => 'Resumen', 'de' => 'Übersicht', 'nl' => 'Overzicht', 'fr' => 'Aperçu', 'it' => 'Panoramica', 'pt' => 'Resumo'], 'admin_hub', 'ROLE_ADMIN'],
                    [20, 'Ops overview', ['en' => 'Ops overview', 'es' => 'Resumen ops', 'de' => 'Ops-Übersicht', 'nl' => 'Ops-overzicht', 'fr' => 'Vue ops', 'it' => 'Panoramica ops', 'pt' => 'Visão ops'], 'admin_ops_overview', 'ROLE_ADMIN'],
                ],
            ],
            [
                20,
                'Access',
                [
                    'en' => 'Access',
                    'es' => 'Acceso',
                    'de' => 'Zugriff',
                    'nl' => 'Toegang',
                    'fr' => 'Accès',
                    'it' => 'Accesso',
                    'pt' => 'Acesso',
                ],
                [
                    [10, 'Users', ['en' => 'Users', 'es' => 'Usuarios', 'de' => 'Benutzer', 'nl' => 'Gebruikers', 'fr' => 'Utilisateurs', 'it' => 'Utenti', 'pt' => 'Utilizadores'], 'admin_users', 'ROLE_ADMIN'],
                    [20, 'Groups', ['en' => 'Groups', 'es' => 'Grupos', 'de' => 'Gruppen', 'nl' => 'Groepen', 'fr' => 'Groupes', 'it' => 'Gruppi', 'pt' => 'Grupos'], 'admin_groups', 'ROLE_ADMIN'],
                    [30, 'Projects', ['en' => 'Projects', 'es' => 'Proyectos', 'de' => 'Projekte', 'nl' => 'Projecten', 'fr' => 'Projets', 'it' => 'Progetti', 'pt' => 'Projetos'], 'admin_projects', 'ROLE_ADMIN'],
                ],
            ],
            [
                30,
                'Instance',
                [
                    'en' => 'Instance',
                    'es' => 'Instancia',
                    'de' => 'Instanz',
                    'nl' => 'Instantie',
                    'fr' => 'Instance',
                    'it' => 'Istanza',
                    'pt' => 'Instância',
                ],
                [
                    [10, 'Appearance', ['en' => 'Appearance', 'es' => 'Apariencia', 'de' => 'Erscheinungsbild', 'nl' => 'Weergave', 'fr' => 'Apparence', 'it' => 'Aspetto', 'pt' => 'Aparência'], 'settings_appearance', 'ROLE_ADMIN'],
                    [20, 'Mailer', ['en' => 'Mailer', 'es' => 'Correo', 'de' => 'Mailer', 'nl' => 'Mailer', 'fr' => 'Mailer', 'it' => 'Mailer', 'pt' => 'Mailer'], 'settings_mailer', 'ROLE_ADMIN'],
                    [30, 'Mercure', ['en' => 'Mercure', 'es' => 'Mercure', 'de' => 'Mercure', 'nl' => 'Mercure', 'fr' => 'Mercure', 'it' => 'Mercure', 'pt' => 'Mercure'], 'settings_mercure', 'ROLE_ADMIN'],
                    [40, 'Social login', ['en' => 'Social login', 'es' => 'Login social', 'de' => 'Social Login', 'nl' => 'Sociale login', 'fr' => 'Connexion sociale', 'it' => 'Login social', 'pt' => 'Login social'], 'admin_social_login', 'ROLE_ADMIN'],
                    [50, 'Ops defaults', ['en' => 'Ops defaults', 'es' => 'Límites ops', 'de' => 'Ops-Standards', 'nl' => 'Ops-standaarden', 'fr' => 'Limites ops', 'it' => 'Limiti ops', 'pt' => 'Limites ops'], 'settings_ops_defaults', 'ROLE_ADMIN'],
                    [60, 'Instance config', ['en' => 'Instance config', 'es' => 'Exportar / importar', 'de' => 'Instanzexport', 'nl' => 'Instantie-export', 'fr' => 'Export / import', 'it' => 'Export / import', 'pt' => 'Exportar / importar'], 'settings_instance_config', 'ROLE_ADMIN'],
                    [70, 'Setup', ['en' => 'Setup', 'es' => 'Setup inicial', 'de' => 'Einrichtung', 'nl' => 'Setup', 'fr' => 'Setup initial', 'it' => 'Setup iniziale', 'pt' => 'Setup inicial'], 'nowo_site_backup_setup', 'ROLE_ADMIN'],
                ],
            ],
            [
                40,
                'Navigation & legal',
                [
                    'en' => 'Navigation & legal',
                    'es' => 'Navegación y legal',
                    'de' => 'Navigation & Rechtliches',
                    'nl' => 'Navigatie & juridisch',
                    'fr' => 'Navigation et légal',
                    'it' => 'Navigazione e legale',
                    'pt' => 'Navegação e legal',
                ],
                [
                    [10, 'Menus', ['en' => 'Menus', 'es' => 'Menús', 'de' => 'Menüs', 'nl' => 'Menu’s', 'fr' => 'Menus', 'it' => 'Menu', 'pt' => 'Menus'], 'nowo_dashboard_menu_dashboard_index', 'ROLE_ADMIN'],
                    [20, 'Breadcrumbs', ['en' => 'Breadcrumbs', 'es' => 'Migas de pan', 'de' => 'Brotkrumen', 'nl' => 'Broodkruimels', 'fr' => 'Fil d’Ariane', 'it' => 'Breadcrumb', 'pt' => 'Navegação'], 'nowo_breadcrumb_kit_dashboard_collections_index', 'ROLE_ADMIN'],
                    [30, 'Cookie consent', ['en' => 'Cookie consent', 'es' => 'Consentimiento de cookies', 'de' => 'Cookie-Einwilligung', 'nl' => 'Cookie-toestemming', 'fr' => 'Consentement cookies', 'it' => 'Consenso cookie', 'pt' => 'Consentimento de cookies'], 'admin_cookie_consent', 'ROLE_ADMIN'],
                    [40, 'Locale routes', ['en' => 'Locale routes', 'es' => 'Rutas por idioma', 'de' => 'Locale-Routen', 'nl' => 'Locale-routes', 'fr' => 'Routes par locale', 'it' => 'Route per locale', 'pt' => 'Rotas por locale'], 'nowo_routing_kit_panel', 'ROLE_ADMIN'],
                ],
            ],
            [
                50,
                'Observability',
                [
                    'en' => 'Observability',
                    'es' => 'Observabilidad',
                    'de' => 'Observability',
                    'nl' => 'Observability',
                    'fr' => 'Observabilité',
                    'it' => 'Osservabilità',
                    'pt' => 'Observabilidade',
                ],
                [
                    [10, 'HTTP log', ['en' => 'HTTP log', 'es' => 'Log HTTP', 'de' => 'HTTP-Protokoll', 'nl' => 'HTTP-log', 'fr' => 'Journal HTTP', 'it' => 'Log HTTP', 'pt' => 'Log HTTP'], 'nowo_http_log_admin_index', 'ROLE_ADMIN'],
                    [20, 'API docs', ['en' => 'API docs', 'es' => 'Docs API', 'de' => 'API-Doku', 'nl' => 'API-docs', 'fr' => 'Docs API', 'it' => 'Documentazione API', 'pt' => 'Docs da API'], 'admin_api_doc', 'ROLE_ADMIN'],
                ],
            ],
        ];

        $changed = false;
        $menu = $this->menuRepository->findOneByCodeAndContext('administration', null);
        if (!$menu instanceof Menu) {
            $menu = new Menu();
            $menu->setCode('administration');
            $menu->setContext(null);
            $menu->setName('Administration');
            $menu->setUlId('administration-menu-navigation');
            $this->entityManager->persist($menu);
            $changed = true;
        }

        $changed = $this->applyBeaconNavClasses($menu) || $changed;

        $checkerId = SecurityIsGrantedMenuPermissionChecker::class;
        if ($menu->getPermissionChecker() !== $checkerId) {
            $menu->setPermissionChecker($checkerId);
            $changed = true;
        }

        $wantedRoutes = [];
        $wantedSectionLabels = [];

        foreach ($sections as [$sectionPosition, $sectionLabel, $sectionTranslations, $children]) {
            $wantedSectionLabels[] = $sectionLabel;
            $section = $this->findSectionByLabel($menu, $sectionLabel);
            if (!$section instanceof MenuItem) {
                $section = $this->section($menu, $sectionPosition, $sectionLabel, $sectionTranslations);
                $changed = true;
            } elseif ($this->syncSection($section, $sectionPosition, $sectionLabel, $sectionTranslations)) {
                $changed = true;
            }

            /** @var list<array{0: int, 1: string, 2: array<string, string>, 3: string, 4: string}> $children */
            foreach ($children as [$position, $label, $translations, $routeName, $permission]) {
                $wantedRoutes[] = $routeName;
                $existing = $this->findItemByRoute($menu, $routeName);
                if ($existing instanceof MenuItem) {
                    if ($this->syncLink($existing, $section, $position, $label, $translations, $permission)) {
                        $changed = true;
                    }
                    continue;
                }
                $item = $this->link($menu, $position, $label, $translations, $routeName, $section);
                $item->setPermissionKey($permission);
                $changed = true;
            }
        }

        foreach ($menu->getItems()->toArray() as $item) {
            if ($item->getItemType() === MenuItem::ITEM_TYPE_SECTION) {
                if (!\in_array($item->getLabel(), $wantedSectionLabels, true)) {
                    $menu->removeItem($item);
                    $this->entityManager->remove($item);
                    $changed = true;
                }
                continue;
            }
            $routeName = $item->getRouteName();
            if (\is_string($routeName) && !\in_array($routeName, $wantedRoutes, true)) {
                $menu->removeItem($item);
                $this->entityManager->remove($item);
                $changed = true;
            }
        }

        return $changed;
    }

    private function applyBeaconNavClasses(Menu $menu): bool
    {
        $changed = false;
        $wanted = [
            'classMenu' => 'beacon-nav',
            'classItem' => 'beacon-nav__item',
            'classLink' => 'beacon-nav__link',
            'classCurrent' => 'is-current',
            'classBranchExpanded' => 'is-branch-current',
            'classSection' => 'beacon-nav__section',
            'classSectionLabel' => 'beacon-nav__section-label',
            'classSectionChildren' => 'beacon-nav__children',
            'classSectionChildItem' => 'beacon-nav__item',
            'classSectionChildLink' => 'beacon-nav__link',
            'classChildren' => 'beacon-nav__children',
            'classHasChildren' => 'has-children',
            'classExpanded' => 'is-expanded',
            'classCollapsed' => 'is-collapsed',
        ];

        foreach ($wanted as $property => $value) {
            $getter = 'get'.ucfirst($property);
            $setter = 'set'.ucfirst($property);
            if ($menu->{$getter}() !== $value) {
                $menu->{$setter}($value);
                $changed = true;
            }
        }

        if ($menu->getNestedCollapsible() !== true) {
            $menu->setNestedCollapsible(true);
            $changed = true;
        }
        if ($menu->getNestedCollapsibleSections() !== true) {
            $menu->setNestedCollapsibleSections(true);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param list<array{0: int, 1: string, 2: array<string, string>, 3: string, 4: string|null}> $definitions
     */
    private function ensureFlatMenu(string $code, string $name, string $ulId, array $definitions): bool
    {
        $changed = false;
        $menu = $this->menuRepository->findOneByCodeAndContext($code, null);
        if (!$menu instanceof Menu) {
            $menu = new Menu();
            $menu->setCode($code);
            $menu->setContext(null);
            $menu->setName($name);
            $menu->setUlId($ulId);
            $menu->setClassMenu('beacon-nav');
            $menu->setClassItem('beacon-nav__item');
            $menu->setClassLink('beacon-nav__link');
            $menu->setClassCurrent('is-current');
            $this->entityManager->persist($menu);
            $changed = true;
        }

        $checkerId = SecurityIsGrantedMenuPermissionChecker::class;
        if ($menu->getPermissionChecker() !== $checkerId) {
            $menu->setPermissionChecker($checkerId);
            $changed = true;
        }

        $wantedRoutes = [];
        foreach ($definitions as [$position, $label, $translations, $routeName, $permission]) {
            $wantedRoutes[] = $routeName;
            $existing = $this->findItemByRoute($menu, $routeName);
            if ($existing instanceof MenuItem) {
                if ($this->syncLink($existing, null, $position, $label, $translations, $permission)) {
                    $changed = true;
                }
                continue;
            }
            $item = $this->link($menu, $position, $label, $translations, $routeName, null);
            if (\is_string($permission)) {
                $item->setPermissionKey($permission);
            }
            $changed = true;
        }

        foreach ($menu->getItems()->toArray() as $item) {
            $routeName = $item->getRouteName();
            if (\is_string($routeName) && !\in_array($routeName, $wantedRoutes, true)) {
                $menu->removeItem($item);
                $this->entityManager->remove($item);
                $changed = true;
            }
        }

        return $changed;
    }

    private function findItemByRoute(Menu $menu, string $routeName): ?MenuItem
    {
        foreach ($menu->getItems() as $item) {
            if ($item->getRouteName() === $routeName) {
                return $item;
            }
        }

        return null;
    }

    private function findSectionByLabel(Menu $menu, string $label): ?MenuItem
    {
        foreach ($menu->getItems() as $item) {
            if ($item->getItemType() === MenuItem::ITEM_TYPE_SECTION && $item->getLabel() === $label) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $translations
     */
    private function syncSection(
        MenuItem $item,
        int $position,
        string $label,
        array $translations,
    ): bool {
        $changed = false;
        if ($item->getPosition() !== $position) {
            $item->setPosition($position);
            $changed = true;
        }
        if ($item->getLabel() !== $label) {
            $item->setLabel($label);
            $changed = true;
        }
        if ($item->getTranslations() !== $translations) {
            $item->setTranslations($translations);
            $changed = true;
        }
        if ($item->getParent() !== null) {
            $item->setParent(null);
            $changed = true;
        }
        if ($item->getSectionCollapsible() !== true) {
            $item->setSectionCollapsible(true);
            $changed = true;
        }
        if ($item->getPermissionKey() !== 'ROLE_ADMIN') {
            $item->setPermissionKey('ROLE_ADMIN');
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param array<string, string> $translations
     */
    private function syncLink(
        MenuItem $item,
        ?MenuItem $parent,
        int $position,
        string $label,
        array $translations,
        ?string $permission,
    ): bool {
        $changed = false;
        if ($item->getPosition() !== $position) {
            $item->setPosition($position);
            $changed = true;
        }
        if ($item->getLabel() !== $label) {
            $item->setLabel($label);
            $changed = true;
        }
        if ($item->getTranslations() !== $translations) {
            $item->setTranslations($translations);
            $changed = true;
        }
        if ($item->getParent() !== $parent) {
            $item->setParent($parent);
            $changed = true;
        }
        if ($item->getItemType() !== MenuItem::ITEM_TYPE_LINK) {
            $item->setItemType(MenuItem::ITEM_TYPE_LINK);
            $changed = true;
        }
        if ($item->getLinkType() !== MenuItem::LINK_TYPE_ROUTE) {
            $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
            $changed = true;
        }
        $wantedPermission = \is_string($permission) ? $permission : null;
        if ($item->getPermissionKey() !== $wantedPermission) {
            $item->setPermissionKey($wantedPermission);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param array<string, string> $translations
     */
    private function section(
        Menu $menu,
        int $position,
        string $label,
        array $translations,
    ): MenuItem {
        $item = new MenuItem();
        $item->setPosition($position);
        $item->setLabel($label);
        $item->setTranslations($translations);
        $item->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $item->setSectionCollapsible(true);
        $item->setPermissionKey('ROLE_ADMIN');
        $menu->addItem($item);

        return $item;
    }

    /**
     * @param array<string, string> $translations
     */
    private function link(
        Menu $menu,
        int $position,
        string $label,
        array $translations,
        string $routeName,
        ?MenuItem $parent,
    ): MenuItem {
        $item = new MenuItem();
        $item->setPosition($position);
        $item->setLabel($label);
        $item->setTranslations($translations);
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName($routeName);
        $item->setParent($parent);
        $menu->addItem($item);

        return $item;
    }
}
