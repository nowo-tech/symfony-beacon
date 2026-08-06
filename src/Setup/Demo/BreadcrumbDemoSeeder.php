<?php

declare(strict_types=1);

namespace App\Setup\Demo;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbCollection;
use Nowo\BreadcrumbKitBundle\Entity\BreadcrumbItem;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;

/**
 * Seeds / syncs the default breadcrumb collection for the Beacon app shell.
 */
final readonly class BreadcrumbDemoSeeder
{
    private const string FIXTURE_FILE = 'breadcrumbs.default.json';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BreadcrumbCollectionRepository $collectionRepository,
        private DemoFixtureLoader $fixtureLoader,
    ) {
    }

    /**
     * @return bool true when any collection or item was created / updated
     */
    public function seedIfEmpty(): bool
    {
        $fixture = $this->fixtureLoader->load(self::FIXTURE_FILE);
        $collectionData = $this->requireArray($fixture, 'collection', 'root');
        $itemsData = $this->requireList($fixture, 'items', 'root');

        $changed = false;
        $collection = $this->collectionRepository->findOneByCodeAndContextKey(
            $this->requireString($collectionData, 'code', 'collection'),
            $this->requireString($collectionData, 'contextKey', 'collection'),
        );

        if (!$collection instanceof BreadcrumbCollection) {
            $collection = new BreadcrumbCollection();
            $collection->setCode($this->requireString($collectionData, 'code', 'collection'));
            $collection->setContextKey($this->requireString($collectionData, 'contextKey', 'collection'));
            $collection->setName($this->requireString($collectionData, 'name', 'collection'));
            $collection->setSeparatorIcon($this->requireString($collectionData, 'separatorIcon', 'collection'));
            $collection->setClassList($this->requireString($collectionData, 'classList', 'collection'));
            $collection->setClassItem($this->requireString($collectionData, 'classItem', 'collection'));
            $collection->setClassSeparator($this->requireString($collectionData, 'classSeparator', 'collection'));
            $collection->setClassCurrent($this->requireString($collectionData, 'classCurrent', 'collection'));
            $collection->setResponsiveConfig($this->requireArray($collectionData, 'responsive', 'collection'));
            $this->entityManager->persist($collection);
            $changed = true;
        }

        $itemsByRoute = [];
        foreach ($itemsData as $index => $itemData) {
            if (!is_array($itemData)) {
                throw new InvalidArgumentException(sprintf('Fixture "%s" has a non-object item at index %d.', self::FIXTURE_FILE, $index));
            }

            $routeName = $this->requireString($itemData, 'route', sprintf('items[%d]', $index));
            $parentRoute = $this->requireNullableString($itemData, 'parent', sprintf('items[%d]', $index));
            $parent = null;
            if (null !== $parentRoute) {
                $parent = $itemsByRoute[$parentRoute] ?? throw new InvalidArgumentException(
                    sprintf('Fixture "%s" references unknown parent route "%s" for "%s".', self::FIXTURE_FILE, $parentRoute, $routeName),
                );
            }

            $itemsByRoute[$routeName] = $this->ensureItem(
                $collection,
                $routeName,
                $this->requireString($itemData, 'label', sprintf('items[%d]', $index)),
                $this->requireTranslations($itemData, 'translations', sprintf('items[%d]', $index)),
                $parent,
                $this->requireStringList($itemData, 'dynamicParamKeys', sprintf('items[%d]', $index)),
                $changed,
            );
        }

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

    /**
     * @param array<mixed> $source
     *
     * @return list<string>
     */
    private function requireStringList(array $source, string $key, string $context): array
    {
        $value = $this->requireList($source, $key, $context);
        foreach ($value as $entry) {
            if (!is_string($entry)) {
                throw new InvalidArgumentException(sprintf('Fixture "%s" expects %s.%s to contain only strings.', self::FIXTURE_FILE, $context, $key));
            }
        }

        return $value;
    }
}
