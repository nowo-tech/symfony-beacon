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
            ['en' => 'Projects', 'es' => 'Proyectos'],
            null,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_new',
            'New project',
            ['en' => 'New project', 'es' => 'Nuevo proyecto'],
            $projects,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'app.swagger_ui',
            'API docs',
            ['en' => 'API docs', 'es' => 'Docs API'],
            $projects,
            [],
            $changed,
        );

        $project = $this->ensureItem(
            $collection,
            'project_show',
            'Project',
            ['en' => 'Project', 'es' => 'Proyecto'],
            $projects,
            ['id'],
            $changed,
        );

        $settings = $this->ensureItem(
            $collection,
            'project_settings',
            'Settings',
            ['en' => 'Settings', 'es' => 'Configuración'],
            $project,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_notification_help',
            'Notification guides',
            ['en' => 'Notification guides', 'es' => 'Guías de notificaciones'],
            $settings,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'project_notification_new',
            'New notification',
            ['en' => 'New notification', 'es' => 'Nueva notificación'],
            $settings,
            ['id'],
            $changed,
        );

        $issues = $this->ensureItem(
            $collection,
            'issue_index',
            'Issues',
            ['en' => 'Issues', 'es' => 'Incidencias'],
            $project,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'issue_show',
            'Issue',
            ['en' => 'Issue', 'es' => 'Incidencia'],
            $issues,
            ['projectId', 'id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'event_show',
            'Event',
            ['en' => 'Event', 'es' => 'Evento'],
            $issues,
            ['projectId', 'eventId'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'analytics_show',
            'Analytics',
            ['en' => 'Analytics', 'es' => 'Analítica'],
            $project,
            ['id'],
            $changed,
        );

        $performance = $this->ensureItem(
            $collection,
            'performance_index',
            'Performance',
            ['en' => 'Performance', 'es' => 'Rendimiento'],
            $project,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'performance_show',
            'Transaction',
            ['en' => 'Transaction', 'es' => 'Transacción'],
            $performance,
            ['projectId', 'id'],
            $changed,
        );

        $admin = $this->ensureItem(
            $collection,
            'admin_hub',
            'Administration',
            ['en' => 'Administration', 'es' => 'Administración'],
            null,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_users',
            'Users',
            ['en' => 'Users', 'es' => 'Usuarios'],
            $admin,
            [],
            $changed,
        );

        $groups = $this->ensureItem(
            $collection,
            'admin_groups',
            'Groups',
            ['en' => 'Groups', 'es' => 'Grupos'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_groups_new',
            'New group',
            ['en' => 'New group', 'es' => 'Nuevo grupo'],
            $groups,
            [],
            $changed,
        );

        $groupShow = $this->ensureItem(
            $collection,
            'admin_groups_show',
            'Group',
            ['en' => 'Group', 'es' => 'Grupo'],
            $groups,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'admin_groups_edit',
            'Edit',
            ['en' => 'Edit', 'es' => 'Editar'],
            $groupShow,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'settings_appearance',
            'Appearance',
            ['en' => 'Appearance', 'es' => 'Apariencia'],
            $admin,
            [],
            $changed,
        );

        $menus = $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_index',
            'Menus',
            ['en' => 'Menus', 'es' => 'Menús'],
            $admin,
            [],
            $changed,
        );

        $menuShow = $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_show',
            'Menu',
            ['en' => 'Menu', 'es' => 'Menú'],
            $menus,
            ['id'],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_dashboard_menu_dashboard_show_items_reorder',
            'Reorder',
            ['en' => 'Reorder', 'es' => 'Reordenar'],
            $menuShow,
            ['id'],
            $changed,
        );

        $breadcrumbs = $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_collections_index',
            'Breadcrumbs',
            ['en' => 'Breadcrumbs', 'es' => 'Migas'],
            $admin,
            [],
            $changed,
        );

        $this->ensureItem(
            $collection,
            'nowo_breadcrumb_kit_dashboard_items_index',
            'Items',
            ['en' => 'Items', 'es' => 'Ítems'],
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
