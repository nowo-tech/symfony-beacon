<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveReturnTagIncompatibleWithNativeTypeRector;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

/*
 * Rector — semantic upgrades only (PHP 8.5, dead code, types, Symfony attributes).
 *
 * Formatting, imports, native `\fn()`, union order (`string|null`), and PER-CS /
 * Symfony style belong to PHP-CS-Fixer. Do not enable SetList::CODING_STYLE,
 * withImportNames(), or CS-Fixer @PHP*Migration sets (Rector owns language level).
 *
 * Always apply CS Fixer after Rector (`make rector-fix` / composer rector-fix).
 *
 * PHPStan owns array-shape / generic docs; Rector must not strip
 * `@return PaginationArray`-style aliases or churn LiveComponent/controller DI.
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
    // Ceiling = composer.json "php": ">=8.5" / FrankenPHP image / CI matrix. Do not
    // also register LevelSetList::UP_TO_PHP_85 — withPhpSets() already loads that stack.
    ->withPhpSets(php85: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        // Rector 2.6.x removed SymfonySetList::SYMFONY_* version constants.
        // Do not enable withComposerBased(symfony: true) yet: it would churn Autowire('%…%'),
        // Twig AsTwigFunction, RequestStack test constructors, and User::eraseCredentials().
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    // Do not enable PHPUnit code-quality sets here: ReplaceTestAnnotationWithPrefixedFunctionRector
    // can mis-read prose like "when@test" in PHPDoc and rename helpers to test* (breaks PHPUnit).
    // PHP-CS-Fixer @Symfony:risky owns php_unit_test_annotation style.
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withParallel()
    ->withIndent(' ', 4)
;
