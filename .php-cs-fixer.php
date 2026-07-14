<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
        __DIR__ . '/common',
        __DIR__ . '/helper',
        __DIR__ . '/constants',
    ])
    ->name('*.php')
    ->notName('*.blade.php');

return (new Config())
    ->setRules([
        "@PSR12" => true,
        "elseif" => false,
        "array_syntax" => [
            "syntax" => "short",
        ],
        "single_quote" => false,
        "single_line_throw" => false,
        "no_unused_imports" => true,
        "ordered_imports" => [
            "sort_algorithm" => "alpha",
            "case_sensitive" => true,
        ],
        "trailing_comma_in_multiline" => true,
        "braces_position" => [
            "classes_opening_brace" => "same_line",
            "functions_opening_brace" => "same_line",
            "anonymous_functions_opening_brace" => "same_line",
            "control_structures_opening_brace" => "same_line",
        ],
        "new_with_parentheses" => [
            "anonymous_class" => false,
        ],
        "single_blank_line_at_eof" => false,
        "single_trait_insert_per_statement" => false,
        "blank_line_after_opening_tag" => true,
        "no_blank_lines_after_class_opening" => false,
        "binary_operator_spaces" => [
            "operators" => [
                "=>" => "single_space",
            ],
        ],
        'object_operator_without_whitespace' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays'],
        ],
    ])
    ->setFinder($finder);

// VSCode Settings

// => Install package friendsofphp/php-cs-fixer with composer

// => Install the extension "junstyle.php-cs-fixer" to use these settings Vscode extention

// => Add these settings to your settings.json file
//   "editor.formatOnSave": true,
//   "php-cs-fixer.executablePath": "${workspaceFolder}/vendor/bin/php-cs-fixer",
//   "php-cs-fixer.onsave": true,
//   "php-cs-fixer.rules": "@PSR12",
//   "php-cs-fixer.config": ".php-cs-fixer.php;.php-cs-fixer.dist.php;.php_cs;.php_cs.dist",
//   "php-cs-fixer.ignorePHPVersion": false,
//   "php-cs-fixer.exclude": [],
//   "php-cs-fixer.autoFixByBracket": false,
//   "php-cs-fixer.autoFixBySemicolon": false,
//   "php-cs-fixer.formatHtml": false,
//   "php-cs-fixer.documentFormattingProvider": true,
//   "[php]": {
//     "editor.defaultFormatter": "junstyle.php-cs-fixer"
//   }

//./vendor/bin/php-cs-fixer fix
