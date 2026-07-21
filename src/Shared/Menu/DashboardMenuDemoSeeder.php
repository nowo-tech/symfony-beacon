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
        $changed = $this->ensureMenu(
            'dashboard',
            'Dashboard',
            'dashboard-menu-navigation',
            [
                [10, 'Projects', ['en' => 'Projects', 'es' => 'Proyectos', 'de' => 'Projekte', 'nl' => 'Projecten', 'fr' => 'Projets', 'it' => 'Progetti', 'pt' => 'Projetos'], 'dashboard_home', null],
                [30, 'API docs', ['en' => 'API docs', 'es' => 'Docs API', 'de' => 'API-Doku', 'nl' => 'API-docs', 'fr' => 'Docs API', 'it' => 'Documentazione API', 'pt' => 'Docs da API'], 'app.swagger_ui', null],
            ],
        );

        $changed = $this->ensureMenu(
            'preferences',
            'Preferences',
            'preferences-menu-navigation',
            [
                [10, 'Profile', ['en' => 'Profile', 'es' => 'Perfil', 'de' => 'Profil', 'nl' => 'Profiel', 'fr' => 'Profil', 'it' => 'Profilo', 'pt' => 'Perfil'], 'account_profile', null],
                [20, 'Security', ['en' => 'Security', 'es' => 'Seguridad', 'de' => 'Sicherheit', 'nl' => 'Beveiliging', 'fr' => 'Sécurité', 'it' => 'Sicurezza', 'pt' => 'Segurança'], 'account_security', null],
                [30, 'Display', ['en' => 'Display', 'es' => 'Interfaz', 'de' => 'Anzeige', 'nl' => 'Weergave', 'fr' => 'Affichage', 'it' => 'Visualizzazione', 'pt' => 'Interface'], 'account_display', null],
            ],
        ) || $changed;

        $changed = $this->ensureMenu(
            'administration',
            'Administration',
            'administration-menu-navigation',
            [
                [10, 'Overview', ['en' => 'Overview', 'es' => 'Resumen', 'de' => 'Übersicht', 'nl' => 'Overzicht', 'fr' => 'Aperçu', 'it' => 'Panoramica', 'pt' => 'Resumo'], 'admin_hub', 'ROLE_ADMIN'],
                [20, 'Users', ['en' => 'Users', 'es' => 'Usuarios', 'de' => 'Benutzer', 'nl' => 'Gebruikers', 'fr' => 'Utilisateurs', 'it' => 'Utenti', 'pt' => 'Utilizadores'], 'admin_users', 'ROLE_ADMIN'],
                [25, 'Groups', ['en' => 'Groups', 'es' => 'Grupos', 'de' => 'Gruppen', 'nl' => 'Groepen', 'fr' => 'Groupes', 'it' => 'Gruppi', 'pt' => 'Grupos'], 'admin_groups', 'ROLE_ADMIN'],
                [27, 'Projects', ['en' => 'Projects', 'es' => 'Proyectos', 'de' => 'Projekte', 'nl' => 'Projecten', 'fr' => 'Projets', 'it' => 'Progetti', 'pt' => 'Projetos'], 'admin_projects', 'ROLE_ADMIN'],
                [30, 'Appearance', ['en' => 'Appearance', 'es' => 'Apariencia', 'de' => 'Erscheinungsbild', 'nl' => 'Weergave', 'fr' => 'Apparence', 'it' => 'Aspetto', 'pt' => 'Aparência'], 'settings_appearance', 'ROLE_ADMIN'],
                [35, 'Mailer', ['en' => 'Mailer', 'es' => 'Correo', 'de' => 'Mailer', 'nl' => 'Mailer', 'fr' => 'Mailer', 'it' => 'Mailer', 'pt' => 'Mailer'], 'settings_mailer', 'ROLE_ADMIN'],
                [40, 'Menus', ['en' => 'Menus', 'es' => 'Menús', 'de' => 'Menüs', 'nl' => 'Menu’s', 'fr' => 'Menus', 'it' => 'Menu', 'pt' => 'Menus'], 'nowo_dashboard_menu_dashboard_index', 'ROLE_ADMIN'],
                [50, 'Breadcrumbs', ['en' => 'Breadcrumbs', 'es' => 'Migas', 'de' => 'Brotkrumen', 'nl' => 'Broodkruimels', 'fr' => 'Fil d’Ariane', 'it' => 'Breadcrumb', 'pt' => 'Navegação'], 'nowo_breadcrumb_kit_dashboard_collections_index', 'ROLE_ADMIN'],
            ],
        ) || $changed;

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
     * @param list<array{0: int, 1: string, 2: array<string, string>, 3: string, 4: string|null}> $definitions
     */
    private function ensureMenu(string $code, string $name, string $ulId, array $definitions): bool
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
                if ($this->syncItem($existing, $position, $label, $translations, $permission)) {
                    $changed = true;
                }
                continue;
            }
            $item = $this->link($menu, $position, $label, $translations, $routeName);
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

    /**
     * @param array<string, string> $translations
     */
    private function syncItem(
        MenuItem $item,
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
    private function link(
        Menu $menu,
        int $position,
        string $label,
        array $translations,
        string $routeName,
    ): MenuItem {
        $item = new MenuItem();
        $item->setPosition($position);
        $item->setLabel($label);
        $item->setTranslations($translations);
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName($routeName);
        $menu->addItem($item);

        return $item;
    }
}
