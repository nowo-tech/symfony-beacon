<?php

declare(strict_types=1);

namespace App\Setup\Demo;

use App\Shared\Menu\SecurityIsGrantedMenuPermissionChecker;
use Deprecated;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

/**
 * Seeds sidebar menus for the three app sections (dashboard, preferences, administration).
 */
final readonly class DashboardMenuDemoSeeder
{
    private const string FIXTURE_FILE = 'menus.json';

    #[Deprecated(message: 'Use AppSection::Dashboard->menuCode()')]
    public const string MAIN_MENU_CODE = 'dashboard';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MenuRepository $menuRepository,
        private DemoFixtureLoader $fixtureLoader,
    ) {
    }

    /**
     * @return bool true when any menu or item was created / updated
     */
    public function seedIfEmpty(): bool
    {
        $definitions = $this->menuDefinitions();

        $changed = $this->ensureFlatMenu($this->requireMenuDefinition($definitions, 'dashboard', 'flat'));
        $changed = $this->ensureFlatMenu($this->requireMenuDefinition($definitions, 'preferences', 'flat')) || $changed;
        $changed = $this->ensureAdministrationMenu($this->requireMenuDefinition($definitions, 'administration', 'nested')) || $changed;

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
     *
     * @param array<mixed> $definition
     */
    private function ensureAdministrationMenu(array $definition): bool
    {
        $code = $this->requireString($definition, 'code', 'menus[administration]');
        $name = $this->requireString($definition, 'name', 'menus[administration]');
        $ulId = $this->requireString($definition, 'ulId', 'menus[administration]');
        $sections = $this->requireList($definition, 'sections', 'menus[administration]');

        $changed = false;
        $menu = $this->menuRepository->findOneByCodeAndContext($code, null);
        if (!$menu instanceof Menu) {
            $menu = new Menu();
            $menu->setCode($code);
            $menu->setContext(null);
            $menu->setName($name);
            $menu->setUlId($ulId);
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

        foreach ($sections as $index => $sectionData) {
            if (!is_array($sectionData)) {
                throw new InvalidArgumentException(sprintf('Fixture "%s" has a non-object section at index %d.', self::FIXTURE_FILE, $index));
            }

            $sectionContext = sprintf('menus[administration].sections[%d]', $index);
            $sectionPosition = $this->requireInt($sectionData, 'position', $sectionContext);
            $sectionLabel = $this->requireString($sectionData, 'label', $sectionContext);
            $sectionTranslations = $this->requireTranslations($sectionData, 'translations', $sectionContext);
            $children = $this->requireList($sectionData, 'children', $sectionContext);

            $wantedSectionLabels[] = $sectionLabel;
            $section = $this->findSectionByLabel($menu, $sectionLabel);
            if (!$section instanceof MenuItem) {
                $section = $this->section($menu, $sectionPosition, $sectionLabel, $sectionTranslations);
                $changed = true;
            } elseif ($this->syncSection($section, $sectionPosition, $sectionLabel, $sectionTranslations)) {
                $changed = true;
            }

            foreach ($children as $childIndex => $itemData) {
                if (!is_array($itemData)) {
                    throw new InvalidArgumentException(
                        sprintf('Fixture "%s" has a non-object child at %s.children[%d].', self::FIXTURE_FILE, $sectionContext, $childIndex),
                    );
                }

                $itemContext = sprintf('%s.children[%d]', $sectionContext, $childIndex);
                $position = $this->requireInt($itemData, 'position', $itemContext);
                $label = $this->requireString($itemData, 'label', $itemContext);
                $translations = $this->requireTranslations($itemData, 'translations', $itemContext);
                $routeName = $this->requireString($itemData, 'route', $itemContext);
                $permission = $this->requireNullableString($itemData, 'permission', $itemContext);

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
     * @param array<mixed> $definition
     */
    private function ensureFlatMenu(array $definition): bool
    {
        $code = $this->requireString($definition, 'code', 'flat menu');
        $name = $this->requireString($definition, 'name', sprintf('menus[%s]', $code));
        $ulId = $this->requireString($definition, 'ulId', sprintf('menus[%s]', $code));
        $definitions = $this->requireList($definition, 'items', sprintf('menus[%s]', $code));

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
        foreach ($definitions as $index => $itemData) {
            if (!is_array($itemData)) {
                throw new InvalidArgumentException(sprintf('Fixture "%s" has a non-object flat menu item at index %d.', self::FIXTURE_FILE, $index));
            }

            $context = sprintf('menus[%s].items[%d]', $code, $index);
            $position = $this->requireInt($itemData, 'position', $context);
            $label = $this->requireString($itemData, 'label', $context);
            $translations = $this->requireTranslations($itemData, 'translations', $context);
            $routeName = $this->requireString($itemData, 'route', $context);
            $permission = $this->requireNullableString($itemData, 'permission', $context);

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

    /**
     * @return array<string, array<mixed>>
     */
    private function menuDefinitions(): array
    {
        $fixture = $this->fixtureLoader->load(self::FIXTURE_FILE);
        $menus = $this->requireList($fixture, 'menus', 'root');
        $definitions = [];

        foreach ($menus as $index => $menuData) {
            if (!is_array($menuData)) {
                throw new InvalidArgumentException(sprintf('Fixture "%s" has a non-object menu at index %d.', self::FIXTURE_FILE, $index));
            }

            $code = $this->requireString($menuData, 'code', sprintf('menus[%d]', $index));
            $definitions[$code] = $menuData;
        }

        return $definitions;
    }

    /**
     * @param array<string, array<mixed>> $definitions
     *
     * @return array<mixed>
     */
    private function requireMenuDefinition(array $definitions, string $code, string $kind): array
    {
        $definition = $definitions[$code] ?? null;
        if (!is_array($definition)) {
            throw new InvalidArgumentException(sprintf('Fixture "%s" is missing menu "%s".', self::FIXTURE_FILE, $code));
        }

        $actualKind = $this->requireString($definition, 'kind', sprintf('menus[%s]', $code));
        if ($actualKind !== $kind) {
            throw new InvalidArgumentException(
                sprintf('Fixture "%s" expects menu "%s" to have kind "%s", got "%s".', self::FIXTURE_FILE, $code, $kind, $actualKind),
            );
        }

        return $definition;
    }

    /**
     * @param array<mixed> $source
     *
     * @return array<mixed>
     */
    private function requireArray(array $source, string $key, string $context): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Fixture "%s" expects %s.%s to be an object.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     *
     * @return list<mixed>
     */
    private function requireList(array $source, string $key, string $context): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Fixture "%s" expects %s.%s to be a list.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     */
    private function requireString(array $source, string $key, string $context): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Fixture "%s" expects %s.%s to be a string.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     */
    private function requireNullableString(array $source, string $key, string $context): ?string
    {
        $value = $source[$key] ?? null;
        if (null !== $value && !is_string($value)) {
            throw new InvalidArgumentException(sprintf('Fixture "%s" expects %s.%s to be a string or null.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     */
    private function requireInt(array $source, string $key, string $context): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Fixture "%s" expects %s.%s to be an integer.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     *
     * @return array<string, string>
     */
    private function requireTranslations(array $source, string $key, string $context): array
    {
        $value = $this->requireArray($source, $key, $context);
        $translations = [];
        foreach ($value as $locale => $translation) {
            if (!is_string($locale) || !is_string($translation)) {
                throw new InvalidArgumentException(
                    sprintf('Fixture "%s" expects %s.%s to be a string map.', self::FIXTURE_FILE, $context, $key),
                );
            }
            $translations[$locale] = $translation;
        }

        return $translations;
    }
}
