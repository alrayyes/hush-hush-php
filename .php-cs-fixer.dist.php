<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude('Generated')
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'declare_strict_types' => true,
        'final_class' => false,
        'php_unit_test_class_requires_covers' => false,
        // Disagrees with phpcs's PSR12 sniff, which wants the closing brace
        // of an empty class/method body on its own line — phpcs owns PSR12
        // per rules/php.md, so this fixer doesn't get to override it.
        'single_line_empty_body' => false,
        // Aligning every @param column pushes a long inline array-shape type
        // (e.g. `array{objectId?: string, ...}`) so far right that phpcs's
        // 120-column sniff fails on the wrapped description underneath it.
        'phpdoc_align' => false,
        // @PhpCsFixer defaults to no space around `.`; PSR12 (phpcs's floor)
        // wants one on each side. phpcs owns PSR12 per rules/php.md.
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder);
