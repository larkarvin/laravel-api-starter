<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app/Console/Commands/MakeApiResource.php', // uses heredoc stubs, skip formatting
    ])

    // Target PHP 8.4 — enables all syntax upgrades up to and including 8.4
    ->withPhpSets(php84: true)

    ->withSets([
        // ── Core quality sets ────────────────────────────────────────────────
        SetList::CODE_QUALITY,         // improve readability and correctness
        SetList::DEAD_CODE,            // remove unreachable / unused code
        SetList::EARLY_RETURN,         // guard clauses instead of deep nesting
        SetList::TYPE_DECLARATION,     // infer and add missing type declarations
        SetList::PRIVATIZATION,        // make classes/methods private where possible
        SetList::STRICT_BOOLEANS,      // replace loose boolean checks with strict ones

        // ── Laravel-specific sets ────────────────────────────────────────────
        LaravelLevelSetList::UP_TO_LARAVEL_130,  // upgrade idioms through L13
        LaravelSetList::LARAVEL_CODE_QUALITY,    // Laravel-specific quality rules
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
    ])

    ->withImportNames(removeUnusedImports: true)

    ->withAttributesSets(
        symfony: false,
        doctrine: false,
        phpunit: true,
    );
