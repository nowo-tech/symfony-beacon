<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveReturnTagIncompatibleWithNativeTypeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

/*
 * Rector for Symfony Beacon.
 *
 * Keep this conservative: PHPStan owns array-shape / generic docs; Rector must not
 * strip `@return Accept|Reject`-style aliases or churn LiveComponent/controller DI
 * in ways that fight Symfony UX conventions.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/var',
        __DIR__.'/vendor',
        __DIR__.'/migrations',
        // Native `array` + `@phpstan-type` aliases (e.g. Accept|Reject) — PHPStan needs the tag.
        RemoveReturnTagIncompatibleWithNativeTypeRector::class,
        // Prefer explicit `null !== $x` / `instanceof self` over FQCN instanceof churn.
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
    // Match composer.json "php": ">=8.5" / FrankenPHP image.
    ->withPhpSets(php85: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        // Rector 2.6.2 removed SymfonySetList::SYMFONY_* version constants.
        // Do not enable withComposerBased(symfony: true) yet: it would churn Autowire('%…%'),
        // Twig AsTwigFunction, RequestStack test constructors, and User::eraseCredentials().
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    // Do not enable PHPUnit code-quality sets here: ReplaceTestAnnotationWithPrefixedFunctionRector
    // can mis-read prose like "when@test" in PHPDoc and rename helpers to test* (breaks PHPUnit).
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withImportNames(removeUnusedImports: true)
;
