<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

/*
 * PHP-CS-Fixer — PER-CS 3.0 (PSR-12 successor) + official Symfony Coding Standards.
 *
 * This tool owns formatting, imports, native `\fn()`, and union/nullable style.
 * Rector owns PHP 8.5 language upgrades, dead code, type inference, and attributes.
 * Do not enable @PHP*Migration / @AutoPHPMigration here (would fight Rector withPhpSets).
 *
 * Run CS Fixer after Rector (`make rector-fix`).
 */
$finder = (new Finder())
    ->files()
    ->name('*.php')
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'node_modules',
        '.docker',
        '.cursor',
        '.specify',
        '.data',
        '.git',
    ])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
        'public/bundles/',
        'public/build/',
    ])
;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        // Symfony:risky removes declare(strict_types); require it (quality + PHP 8.5).
        'declare_strict_types' => true,
        // Import policy — CS-Fixer is the source of truth (Rector must not withImportNames).
        // import_classes true is PER-CS-compatible; Symfony's own default is false.
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        // Overrides @Symfony:risky strict=true so namespaced test hooks keep explicit `\fn()`.
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => false,
        ],
        // Keep PHPStan / generic tags that phpdoc_to_comment would otherwise turn into // comments.
        'phpdoc_to_comment' => [
            'allow_before_return_statement' => false,
            'ignored_tags' => [
                'phpstan-type',
                'phpstan-import-type',
                'phpstan-var',
                'phpstan-param',
                'phpstan-return',
                'phpstan-assert',
                'phpstan-assert-if-true',
                'phpstan-assert-if-false',
                'phpstan-ignore',
                'phpstan-ignore-next-line',
                'phpstan-require-extends',
                'phpstan-require-implements',
                'phpstan-self-out',
                'phpstan-this-out',
                'phpstan-pure',
                'phpstan-impure',
                'template',
                'extends',
                'implements',
                'param-out',
            ],
        ],
        // Keep `@param mixed` / hidden params that PHPStan still needs.
        'no_superfluous_phpdoc_tags' => [
            'allow_hidden_params' => true,
            'allow_mixed' => true,
            'remove_inheritdoc' => true,
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache')
;
