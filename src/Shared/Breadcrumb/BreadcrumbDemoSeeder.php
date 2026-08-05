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

        // Panel aside routes nest under dashboard home so trails are visible
        // (presentation.hide_when_single_root hides lone root crumbs).
        $this->ensureItem(
            $collection,
            'dashboard_assignments',
            'Assignments',
            ['en' => 'Assignments', 'es' => 'Asignaciones', 'de' => 'Zuweisungen', 'nl' => 'Toewijzingen', 'fr' => 'Affectations', 'it' => 'Assegnazioni', 'pt' => 'Atribuições'],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'dashboard_summary',
            'Summary',
            ['en' => 'Summary', 'es' => 'Resumen', 'de' => 'Übersicht', 'nl' => 'Samenvatting', 'fr' => 'Résumé', 'it' => 'Riepilogo', 'pt' => 'Resumo'],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'dashboard_activity',
            'Activity',
            ['en' => 'Activity', 'es' => 'Actividad', 'de' => 'Aktivität', 'nl' => 'Activiteit', 'fr' => 'Activité', 'it' => 'Attività', 'pt' => 'Atividade'],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'dashboard_mentions',
            'Mentions',
            ['en' => 'Mentions', 'es' => 'Menciones', 'de' => 'Erwähnungen', 'nl' => 'Vermeldingen', 'fr' => 'Mentions', 'it' => 'Menzioni', 'pt' => 'Menções'],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'dashboard_alerts',
            'Alerts',
            ['en' => 'Alerts', 'es' => 'Alertas', 'de' => 'Warnungen', 'nl' => 'Meldingen', 'fr' => 'Alertes', 'it' => 'Avvisi', 'pt' => 'Alertas'],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'dashboard_new_in_release',
            'New in release',
            ['en' => 'New in release', 'es' => 'Nuevo en release', 'de' => 'Neu in Release', 'nl' => 'Nieuw in release', 'fr' => 'Nouveau en release', 'it' => 'Nuovo in release', 'pt' => 'Novo na release'],
            $projects,
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

        $this->ensureItem(
            $collection,
            'project_notification_edit',
            'Edit notification',
            ['en' => 'Edit notification', 'es' => 'Editar notificación', 'de' => 'Benachrichtigung bearbeiten', 'nl' => 'Melding bewerken', 'fr' => 'Modifier la notification', 'it' => 'Modifica notifica', 'pt' => 'Editar notificação'],
            $settings,
            ['projectId', 'id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_threshold_rule_new',
            'New threshold rule',
            ['en' => 'New threshold rule', 'es' => 'Nueva regla de umbral', 'de' => 'Neue Schwellwertregel', 'nl' => 'Nieuwe drempelregel', 'fr' => 'Nouvelle règle de seuil', 'it' => 'Nuova regola di soglia', 'pt' => 'Nova regra de limiar'],
            $settings,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_threshold_rule_edit',
            'Edit threshold rule',
            ['en' => 'Edit threshold rule', 'es' => 'Editar regla de umbral', 'de' => 'Schwellwertregel bearbeiten', 'nl' => 'Drempelregel bewerken', 'fr' => 'Modifier la règle de seuil', 'it' => 'Modifica regola di soglia', 'pt' => 'Editar regra de limiar'],
            $settings,
            ['projectId', 'id'],
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

        $this->ensureItem(
            $collection,
            'project_releases',
            'Releases',
            ['en' => 'Releases', 'es' => 'Releases', 'de' => 'Releases', 'nl' => 'Releases', 'fr' => 'Releases', 'it' => 'Release', 'pt' => 'Releases'],
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
            'admin_ops_overview',
            'Ops overview',
            ['en' => 'Ops overview', 'es' => 'Vista ops', 'de' => 'Ops-Übersicht', 'nl' => 'Ops-overzicht', 'fr' => 'Vue ops', 'it' => 'Panoramica ops', 'pt' => 'Visão ops'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_api_doc',
            'API docs',
            ['en' => 'API docs', 'es' => 'Docs API', 'de' => 'API-Doku', 'nl' => 'API-docs', 'fr' => 'Docs API', 'it' => 'Documentazione API', 'pt' => 'Docs da API'],
            $admin,
            [],
            $changed,
        );

        $users = $this->ensureItem(
            $collection,
            'admin_users',
            'Users',
            ['en' => 'Users', 'es' => 'Usuarios', 'de' => 'Benutzer', 'nl' => 'Gebruikers', 'fr' => 'Utilisateurs', 'it' => 'Utenti', 'pt' => 'Utilizadores'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_users_new',
            'New user',
            ['en' => 'New user', 'es' => 'Nuevo usuario', 'de' => 'Neuer Benutzer', 'nl' => 'Nieuwe gebruiker', 'fr' => 'Nouvel utilisateur', 'it' => 'Nuovo utente', 'pt' => 'Novo utilizador'],
            $users,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_users_activity',
            'Activity',
            ['en' => 'Activity', 'es' => 'Actividad', 'de' => 'Aktivität', 'nl' => 'Activiteit', 'fr' => 'Activité', 'it' => 'Attività', 'pt' => 'Atividade'],
            $users,
            ['id'],
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

        $this->ensureItem(
            $collection,
            'settings_mailer',
            'Mailer',
            ['en' => 'Mailer', 'es' => 'Correo', 'de' => 'Mailer', 'nl' => 'Mailer', 'fr' => 'Mailer', 'it' => 'Mailer', 'pt' => 'Mailer'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'settings_mercure',
            'Mercure',
            ['en' => 'Mercure', 'es' => 'Mercure', 'de' => 'Mercure', 'nl' => 'Mercure', 'fr' => 'Mercure', 'it' => 'Mercure', 'pt' => 'Mercure'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'settings_ops_defaults',
            'Ops defaults',
            ['en' => 'Ops defaults', 'es' => 'Valores ops', 'de' => 'Ops-Standards', 'nl' => 'Ops-standaarden', 'fr' => 'Valeurs ops', 'it' => 'Predefiniti ops', 'pt' => 'Predefinições ops'],
            $admin,
            [],
            $changed,
        );

        // Section tabs; same trail label as the ops-defaults redirect entry.
        $this->ensureItem(
            $collection,
            'settings_ops_defaults_section',
            'Ops defaults',
            ['en' => 'Ops defaults', 'es' => 'Valores ops', 'de' => 'Ops-Standards', 'nl' => 'Ops-standaarden', 'fr' => 'Valeurs ops', 'it' => 'Predefiniti ops', 'pt' => 'Predefinições ops'],
            $admin,
            ['section'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'settings_instance_config',
            'Instance config',
            ['en' => 'Instance config', 'es' => 'Configuración de instancia', 'de' => 'Instanzkonfiguration', 'nl' => 'Instantieconfiguratie', 'fr' => 'Configuration d’instance', 'it' => 'Configurazione istanza', 'pt' => 'Configuração da instância'],
            $admin,
            [],
            $changed,
        );

        $socialLogin = $this->ensureItem(
            $collection,
            'admin_social_login',
            'Social login',
            ['en' => 'Social login', 'es' => 'Inicio social', 'de' => 'Social Login', 'nl' => 'Sociale login', 'fr' => 'Connexion sociale', 'it' => 'Login social', 'pt' => 'Login social'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_social_login_new',
            'Add provider',
            ['en' => 'Add provider', 'es' => 'Añadir proveedor', 'de' => 'Anbieter hinzufügen', 'nl' => 'Provider toevoegen', 'fr' => 'Ajouter un fournisseur', 'it' => 'Aggiungi provider', 'pt' => 'Adicionar fornecedor'],
            $socialLogin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_social_login_edit',
            'Edit provider',
            ['en' => 'Edit provider', 'es' => 'Editar proveedor', 'de' => 'Anbieter bearbeiten', 'nl' => 'Provider bewerken', 'fr' => 'Modifier le fournisseur', 'it' => 'Modifica provider', 'pt' => 'Editar fornecedor'],
            $socialLogin,
            ['provider'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_site_backup_setup',
            'Setup',
            ['en' => 'Setup', 'es' => 'Configuración', 'de' => 'Einrichtung', 'nl' => 'Configuratie', 'fr' => 'Configuration', 'it' => 'Configurazione', 'pt' => 'Configuração'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_routing_kit_panel',
            'Locale routes',
            ['en' => 'Locale routes', 'es' => 'Rutas por idioma', 'de' => 'Locale-Routen', 'nl' => 'Locale-routes', 'fr' => 'Routes par locale', 'it' => 'Route per locale', 'pt' => 'Rotas por locale'],
            $admin,
            [],
            $changed,
        );

        $httpLog = $this->ensureItem(
            $collection,
            'nowo_http_log_admin_index',
            'HTTP log',
            ['en' => 'HTTP log', 'es' => 'Log HTTP', 'de' => 'HTTP-Protokoll', 'nl' => 'HTTP-log', 'fr' => 'Journal HTTP', 'it' => 'Log HTTP', 'pt' => 'Log HTTP'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_http_log_admin_show',
            'Request',
            ['en' => 'Request', 'es' => 'Petición', 'de' => 'Anfrage', 'nl' => 'Request', 'fr' => 'Requête', 'it' => 'Richiesta', 'pt' => 'Pedido'],
            $httpLog,
            ['id'],
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

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_menu_new',
            'New menu',
            ['en' => 'New menu', 'es' => 'Nuevo menú', 'de' => 'Neues Menü', 'nl' => 'Nieuw menu', 'fr' => 'Nouveau menu', 'it' => 'Nuovo menu', 'pt' => 'Novo menu'],
            $menus,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_menu_edit',
            'Edit',
            ['en' => 'Edit', 'es' => 'Editar', 'de' => 'Bearbeiten', 'nl' => 'Bewerken', 'fr' => 'Modifier', 'it' => 'Modifica', 'pt' => 'Editar'],
            $menuShow,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_menu_copy',
            'Copy',
            ['en' => 'Copy', 'es' => 'Copiar', 'de' => 'Kopieren', 'nl' => 'Kopiëren', 'fr' => 'Copier', 'it' => 'Copia', 'pt' => 'Copiar'],
            $menuShow,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_item_new',
            'New item',
            ['en' => 'New item', 'es' => 'Nuevo ítem', 'de' => 'Neuer Eintrag', 'nl' => 'Nieuw item', 'fr' => 'Nouvel élément', 'it' => 'Nuovo elemento', 'pt' => 'Novo item'],
            $menuShow,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_item_edit',
            'Edit item',
            ['en' => 'Edit item', 'es' => 'Editar ítem', 'de' => 'Eintrag bearbeiten', 'nl' => 'Item bewerken', 'fr' => 'Modifier l’élément', 'it' => 'Modifica elemento', 'pt' => 'Editar item'],
            $menuShow,
            ['id', 'itemId'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_import',
            'Import',
            ['en' => 'Import', 'es' => 'Importar', 'de' => 'Importieren', 'nl' => 'Importeren', 'fr' => 'Importer', 'it' => 'Importa', 'pt' => 'Importar'],
            $menus,
            [],
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
            'nowo_breadcrumb_kit_dashboard_index',
            'Breadcrumbs',
            ['en' => 'Breadcrumbs', 'es' => 'Migas', 'de' => 'Brotkrumen', 'nl' => 'Broodkruimels', 'fr' => 'Fil d’Ariane', 'it' => 'Breadcrumb', 'pt' => 'Navegação'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_collections_new',
            'New collection',
            ['en' => 'New collection', 'es' => 'Nueva colección', 'de' => 'Neue Sammlung', 'nl' => 'Nieuwe collectie', 'fr' => 'Nouvelle collection', 'it' => 'Nuova collezione', 'pt' => 'Nova coleção'],
            $breadcrumbs,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_collections_edit',
            'Edit',
            ['en' => 'Edit', 'es' => 'Editar', 'de' => 'Bearbeiten', 'nl' => 'Bewerken', 'fr' => 'Modifier', 'it' => 'Modifica', 'pt' => 'Editar'],
            $breadcrumbs,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_import',
            'Import',
            ['en' => 'Import', 'es' => 'Importar', 'de' => 'Importieren', 'nl' => 'Importeren', 'fr' => 'Importer', 'it' => 'Importa', 'pt' => 'Importar'],
            $breadcrumbs,
            [],
            $changed,
        );

        $breadcrumbItems = $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_items_index',
            'Items',
            ['en' => 'Items', 'es' => 'Ítems', 'de' => 'Einträge', 'nl' => 'Items', 'fr' => 'Éléments', 'it' => 'Elementi', 'pt' => 'Itens'],
            $breadcrumbs,
            ['collectionId'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_items_new',
            'New item',
            ['en' => 'New item', 'es' => 'Nuevo ítem', 'de' => 'Neuer Eintrag', 'nl' => 'Nieuw item', 'fr' => 'Nouvel élément', 'it' => 'Nuovo elemento', 'pt' => 'Novo item'],
            $breadcrumbItems,
            ['collectionId'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_items_edit',
            'Edit',
            ['en' => 'Edit', 'es' => 'Editar', 'de' => 'Bearbeiten', 'nl' => 'Bewerken', 'fr' => 'Modifier', 'it' => 'Modifica', 'pt' => 'Editar'],
            $breadcrumbItems,
            ['collectionId', 'id'],
            $changed,
        );

        $cookieConsent = $this->ensureItem(
            $collection,
            'nowo_cookie_consent_config_settings_edit',
            'Cookie consent',
            ['en' => 'Cookie consent', 'es' => 'Consentimiento de cookies', 'de' => 'Cookie-Einwilligung', 'nl' => 'Cookie-toestemming', 'fr' => 'Consentement cookies', 'it' => 'Consenso cookie', 'pt' => 'Consentimento de cookies'],
            $admin,
            ['configId'],
            $changed,
        );

        // Section forms (1.5+); same trail label as the settings redirect entry.
        $this->ensureItem(
            $collection,
            'nowo_cookie_consent_config_settings_section',
            'Cookie consent',
            ['en' => 'Cookie consent', 'es' => 'Consentimiento de cookies', 'de' => 'Cookie-Einwilligung', 'nl' => 'Cookie-toestemming', 'fr' => 'Consentement cookies', 'it' => 'Consenso cookie', 'pt' => 'Consentimento de cookies'],
            $admin,
            ['configId', 'section'],
            $changed,
        );

        $cookieDefinitions = $this->ensureItem(
            $collection,
            'nowo_cookie_consent_cookie_definitions_index',
            'Cookie definitions',
            ['en' => 'Cookie definitions', 'es' => 'Definiciones de cookies', 'de' => 'Cookie-Definitionen', 'nl' => 'Cookie-definities', 'fr' => 'Définitions de cookies', 'it' => 'Definizioni cookie', 'pt' => 'Definições de cookies'],
            $cookieConsent,
            ['configId'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_cookie_consent_cookie_definitions_new',
            'New definition',
            ['en' => 'New definition', 'es' => 'Nueva definición', 'de' => 'Neue Definition', 'nl' => 'Nieuwe definitie', 'fr' => 'Nouvelle définition', 'it' => 'Nuova definizione', 'pt' => 'Nova definição'],
            $cookieDefinitions,
            ['configId'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_cookie_consent_cookie_definitions_edit',
            'Edit',
            ['en' => 'Edit', 'es' => 'Editar', 'de' => 'Bearbeiten', 'nl' => 'Bewerken', 'fr' => 'Modifier', 'it' => 'Modifica', 'pt' => 'Editar'],
            $cookieDefinitions,
            ['configId', 'id'],
            $changed,
        );

        $account = $this->ensureItem(
            $collection,
            'account_profile',
            'Account settings',
            [
                'en' => 'Account settings',
                'es' => 'Ajustes de cuenta',
                'de' => 'Kontoeinstellungen',
                'nl' => 'Accountinstellingen',
                'fr' => 'Paramètres du compte',
                'it' => 'Impostazioni account',
                'pt' => 'Definições da conta',
            ],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_projects',
            'My projects',
            [
                'en' => 'My projects',
                'es' => 'Mis proyectos',
                'de' => 'Meine Projekte',
                'nl' => 'Mijn projecten',
                'fr' => 'Mes projets',
                'it' => 'I miei progetti',
                'pt' => 'Os meus projetos',
            ],
            $account,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_groups',
            'My groups',
            [
                'en' => 'My groups',
                'es' => 'Mis grupos',
                'de' => 'Meine Gruppen',
                'nl' => 'Mijn groepen',
                'fr' => 'Mes groupes',
                'it' => 'I miei gruppi',
                'pt' => 'Os meus grupos',
            ],
            $account,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_privacy',
            'Privacy',
            [
                'en' => 'Privacy',
                'es' => 'Privacidad',
                'de' => 'Datenschutz',
                'nl' => 'Privacy',
                'fr' => 'Confidentialité',
                'it' => 'Privacy',
                'pt' => 'Privacidade',
            ],
            $account,
            [],
            $changed,
        );

        $security = $this->ensureItem(
            $collection,
            'account_security',
            'Security',
            [
                'en' => 'Security',
                'es' => 'Seguridad',
                'de' => 'Sicherheit',
                'nl' => 'Beveiliging',
                'fr' => 'Sécurité',
                'it' => 'Sicurezza',
                'pt' => 'Segurança',
            ],
            $account,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_security_history',
            'Change history',
            [
                'en' => 'Change history',
                'es' => 'Historial de cambios',
                'de' => 'Änderungshistorie',
                'nl' => 'Wijzigingsgeschiedenis',
                'fr' => 'Historique des changements',
                'it' => 'Cronologia modifiche',
                'pt' => 'Histórico de alterações',
            ],
            $security,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_security_activity',
            'Activity',
            [
                'en' => 'Activity',
                'es' => 'Actividad',
                'de' => 'Aktivität',
                'nl' => 'Activiteit',
                'fr' => 'Activité',
                'it' => 'Attività',
                'pt' => 'Atividade',
            ],
            $security,
            [],
            $changed,
        );

        $display = $this->ensureItem(
            $collection,
            'account_display',
            'Display',
            [
                'en' => 'Display',
                'es' => 'Visualización',
                'de' => 'Anzeige',
                'nl' => 'Weergave',
                'fr' => 'Affichage',
                'it' => 'Visualizzazione',
                'pt' => 'Apresentação',
            ],
            $account,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_display_panels',
            'Issue panels',
            [
                'en' => 'Issue panels',
                'es' => 'Paneles de incidencias',
                'de' => 'Issue-Panels',
                'nl' => 'Issuepanelen',
                'fr' => 'Panneaux d’incidents',
                'it' => 'Pannelli issue',
                'pt' => 'Painéis de issues',
            ],
            $display,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_display_tours',
            'Tours',
            [
                'en' => 'Tours',
                'es' => 'Tours',
                'de' => 'Touren',
                'nl' => 'Tours',
                'fr' => 'Visites guidées',
                'it' => 'Tour',
                'pt' => 'Tours',
            ],
            $display,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'account_display_notifications',
            'Notifications',
            [
                'en' => 'Notifications',
                'es' => 'Notificaciones',
                'de' => 'Benachrichtigungen',
                'nl' => 'Meldingen',
                'fr' => 'Notifications',
                'it' => 'Notifiche',
                'pt' => 'Notificações',
            ],
            $display,
            [],
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
