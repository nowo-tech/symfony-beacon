<?php

declare(strict_types=1);

namespace App\Shared\Breadcrumb;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;

/**
 * Seeds / syncs the default breadcrumb collection for the Beacon app shell.
 */
final readonly class BreadcrumbDemoSeeder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BreadcrumbCollectionRepository $collectionRepository,
    ) {
    }

    /**
     * @return bool true when any collection or item was created / updated
     */
    public function seedIfEmpty(): bool
    {
        $changed = false;
        $collection = $this->collectionRepository->findOneByCodeAndContextKey('default', '');
        if (!$collection instanceof BreadcrumbCollection) {
            $collection = new BreadcrumbCollection();
            $collection->setCode('default');
            $collection->setContextKey('');
            $collection->setName('App');
            $collection->setSeparatorIcon('›');
            $collection->setClassList('beacon-breadcrumb');
            $collection->setClassItem('beacon-breadcrumb__item');
            $collection->setClassSeparator('beacon-breadcrumb__sep');
            $collection->setClassCurrent('is-current');
            $collection->setResponsiveConfig(['hide_when_single_root' => true]);
            $this->entityManager->persist($collection);
            $changed = true;
        }

        $projects = $this->ensureItem(
            $collection,
            'dashboard_home',
            'Projects',
            ['en' => 'Projects', 'es' => 'Proyectos', 'de' => 'Projekte', 'nl' => 'Projecten', 'fr' => 'Projets', 'it' => 'Progetti', 'pt' => 'Projetos'],
            null,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_new',
            'New project',
            ['en' => 'New project', 'es' => 'Nuevo proyecto', 'de' => 'Neues Projekt', 'nl' => 'Nieuw project', 'fr' => 'Nouveau projet', 'it' => 'Nuovo progetto', 'pt' => 'Novo projeto'],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'app.swagger_ui',
            'API docs',
            ['en' => 'API docs', 'es' => 'Docs API', 'de' => 'API-Doku', 'nl' => 'API-docs', 'fr' => 'Docs API', 'it' => 'Documentazione API', 'pt' => 'Docs da API'],
            $projects,
            [],
            $changed,
        );

        $project = $this->ensureItem(
            $collection,
            'project_show',
            'Project',
            ['en' => 'Project', 'es' => 'Proyecto', 'de' => 'Projekt', 'nl' => 'Project', 'fr' => 'Projet', 'it' => 'Progetto', 'pt' => 'Projeto'],
            $projects,
            ['id'],
            $changed,
        );

        $settings = $this->ensureItem(
            $collection,
            'project_settings',
            'Settings',
            ['en' => 'Settings', 'es' => 'Configuración', 'de' => 'Einstellungen', 'nl' => 'Instellingen', 'fr' => 'Paramètres', 'it' => 'Impostazioni', 'pt' => 'Definições'],
            $project,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_notification_help',
            'Notification guides',
            ['en' => 'Notification guides', 'es' => 'Guías de notificaciones', 'de' => 'Benachrichtigungsleitfäden', 'nl' => 'Meldingsgidsen', 'fr' => 'Guides de notification', 'it' => 'Guide alle notifiche', 'pt' => 'Guias de notificação'],
            $settings,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_notification_new',
            'New notification',
            ['en' => 'New notification', 'es' => 'Nueva notificación', 'de' => 'Neue Benachrichtigung', 'nl' => 'Nieuwe melding', 'fr' => 'Nouvelle notification', 'it' => 'Nuova notifica', 'pt' => 'Nova notificação'],
            $settings,
            ['id'],
            $changed,
        );

        $issues = $this->ensureItem(
            $collection,
            'issue_index',
            'Issues',
            ['en' => 'Issues', 'es' => 'Incidencias', 'de' => 'Probleme', 'nl' => 'Issues', 'fr' => 'Incidents', 'it' => 'Problemi', 'pt' => 'Incidentes'],
            $project,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'issue_show',
            'Issue',
            ['en' => 'Issue', 'es' => 'Incidencia', 'de' => 'Problem', 'nl' => 'Issue', 'fr' => 'Incident', 'it' => 'Problema', 'pt' => 'Incidente'],
            $issues,
            ['projectId', 'id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'event_show',
            'Event',
            ['en' => 'Event', 'es' => 'Evento', 'de' => 'Ereignis', 'nl' => 'Gebeurtenis', 'fr' => 'Événement', 'it' => 'Evento', 'pt' => 'Evento'],
            $issues,
            ['projectId', 'eventId'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'analytics_show',
            'Analytics',
            ['en' => 'Analytics', 'es' => 'Analítica', 'de' => 'Analysen', 'nl' => 'Analytics', 'fr' => 'Analytique', 'it' => 'Analisi', 'pt' => 'Análises'],
            $project,
            ['id'],
            $changed,
        );

        $performance = $this->ensureItem(
            $collection,
            'performance_index',
            'Performance',
            ['en' => 'Performance', 'es' => 'Rendimiento', 'de' => 'Leistung', 'nl' => 'Prestaties', 'fr' => 'Performance', 'it' => 'Prestazioni', 'pt' => 'Desempenho'],
            $project,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'performance_show',
            'Transaction',
            ['en' => 'Transaction', 'es' => 'Transacción', 'de' => 'Transaktion', 'nl' => 'Transactie', 'fr' => 'Transaction', 'it' => 'Transazione', 'pt' => 'Transação'],
            $performance,
            ['projectId', 'id'],
            $changed,
        );

        $admin = $this->ensureItem(
            $collection,
            'admin_hub',
            'Administration',
            ['en' => 'Administration', 'es' => 'Administración', 'de' => 'Administration', 'nl' => 'Beheer', 'fr' => 'Administration', 'it' => 'Amministrazione', 'pt' => 'Administração'],
            null,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_users',
            'Users',
            ['en' => 'Users', 'es' => 'Usuarios', 'de' => 'Benutzer', 'nl' => 'Gebruikers', 'fr' => 'Utilisateurs', 'it' => 'Utenti', 'pt' => 'Utilizadores'],
            $admin,
            [],
            $changed,
        );

        $groups = $this->ensureItem(
            $collection,
            'admin_groups',
            'Groups',
            ['en' => 'Groups', 'es' => 'Grupos', 'de' => 'Gruppen', 'nl' => 'Groepen', 'fr' => 'Groupes', 'it' => 'Gruppi', 'pt' => 'Grupos'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_groups_new',
            'New group',
            ['en' => 'New group', 'es' => 'Nuevo grupo', 'de' => 'Neue Gruppe', 'nl' => 'Nieuwe groep', 'fr' => 'Nouveau groupe', 'it' => 'Nuovo gruppo', 'pt' => 'Novo grupo'],
            $groups,
            [],
            $changed,
        );

        $groupShow = $this->ensureItem(
            $collection,
            'admin_groups_show',
            'Group',
            ['en' => 'Group', 'es' => 'Grupo', 'de' => 'Gruppe', 'nl' => 'Groep', 'fr' => 'Groupe', 'it' => 'Gruppo', 'pt' => 'Grupo'],
            $groups,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_groups_edit',
            'Edit',
            ['en' => 'Edit', 'es' => 'Editar', 'de' => 'Bearbeiten', 'nl' => 'Bewerken', 'fr' => 'Modifier', 'it' => 'Modifica', 'pt' => 'Editar'],
            $groupShow,
            ['id'],
            $changed,
        );

        $adminProjects = $this->ensureItem(
            $collection,
            'admin_projects',
            'Projects',
            ['en' => 'Projects', 'es' => 'Proyectos', 'de' => 'Projekte', 'nl' => 'Projecten', 'fr' => 'Projets', 'it' => 'Progetti', 'pt' => 'Projetos'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_projects_new',
            'New project',
            ['en' => 'New project', 'es' => 'Nuevo proyecto', 'de' => 'Neues Projekt', 'nl' => 'Nieuw project', 'fr' => 'Nouveau projet', 'it' => 'Nuovo progetto', 'pt' => 'Novo projeto'],
            $adminProjects,
            [],
            $changed,
        );

        $adminProjectShow = $this->ensureItem(
            $collection,
            'admin_projects_show',
            'Project',
            ['en' => 'Project', 'es' => 'Proyecto', 'de' => 'Projekt', 'nl' => 'Project', 'fr' => 'Projet', 'it' => 'Progetto', 'pt' => 'Projeto'],
            $adminProjects,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_projects_edit',
            'Edit',
            ['en' => 'Edit', 'es' => 'Editar', 'de' => 'Bearbeiten', 'nl' => 'Bewerken', 'fr' => 'Modifier', 'it' => 'Modifica', 'pt' => 'Editar'],
            $adminProjectShow,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'settings_appearance',
            'Appearance',
            ['en' => 'Appearance', 'es' => 'Apariencia', 'de' => 'Erscheinungsbild', 'nl' => 'Weergave', 'fr' => 'Apparence', 'it' => 'Aspetto', 'pt' => 'Aparência'],
            $admin,
            [],
            $changed,
        );

        $menus = $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_index',
            'Menus',
            ['en' => 'Menus', 'es' => 'Menús', 'de' => 'Menüs', 'nl' => 'Menu’s', 'fr' => 'Menus', 'it' => 'Menu', 'pt' => 'Menus'],
            $admin,
            [],
            $changed,
        );

        $menuShow = $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_show',
            'Menu',
            ['en' => 'Menu', 'es' => 'Menú', 'de' => 'Menü', 'nl' => 'Menu', 'fr' => 'Menu', 'it' => 'Menu', 'pt' => 'Menu'],
            $menus,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_show_items_reorder',
            'Reorder',
            ['en' => 'Reorder', 'es' => 'Reordenar', 'de' => 'Neu ordnen', 'nl' => 'Herordenen', 'fr' => 'Réorganiser', 'it' => 'Riordina', 'pt' => 'Reordenar'],
            $menuShow,
            ['id'],
            $changed,
        );

        $breadcrumbs = $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_collections_index',
            'Breadcrumbs',
            ['en' => 'Breadcrumbs', 'es' => 'Migas', 'de' => 'Brotkrumen', 'nl' => 'Broodkruimels', 'fr' => 'Fil d’Ariane', 'it' => 'Breadcrumb', 'pt' => 'Navegação'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_items_index',
            'Items',
            ['en' => 'Items', 'es' => 'Ítems', 'de' => 'Einträge', 'nl' => 'Items', 'fr' => 'Éléments', 'it' => 'Elementi', 'pt' => 'Itens'],
            $breadcrumbs,
            ['collectionId'],
            $changed,
        );

        if ($changed) {
            $this->entityManager->flush();
        }

        return $changed;
    }

    /**
     * @param array<string, string> $translations
     * @param list<string>          $dynamicParamKeys
     */
    private function ensureItem(
        BreadcrumbCollection $collection,
        string $routeName,
        string $label,
        array $translations,
        ?BreadcrumbItem $parent,
        array $dynamicParamKeys,
        bool &$changed,
    ): BreadcrumbItem {
        foreach ($collection->getItems() as $existing) {
            if ($existing->getRouteName() === $routeName) {
                $needsUpdate = false;
                if ($existing->getParent() !== $parent) {
                    $existing->setParent($parent);
                    $needsUpdate = true;
                }
                if ($existing->getDynamicParamKeys() !== $dynamicParamKeys) {
                    $existing->setDynamicParamKeys($dynamicParamKeys);
                    $needsUpdate = true;
                }
                if ($existing->getTranslations() !== $translations) {
                    $existing->setTranslations($translations);
                    $needsUpdate = true;
                }
                if ($needsUpdate) {
                    $changed = true;
                }

                return $existing;
            }
        }

        $item = new BreadcrumbItem();
        $item->setRouteName($routeName);
        $item->setStaticRouteParams([]);
        $item->setLabel($label);
        $item->setTranslations($translations);
        $item->setLinkEnabled(true);
        $item->setDynamicParamKeys($dynamicParamKeys);
        $item->setParent($parent);
        $collection->addItem($item);
        $changed = true;

        return $item;
    }
}
