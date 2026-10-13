<?php
/**
 * Magelan_SearchCms - unit test bootstrap for the CI harness.
 */
declare(strict_types=1);

error_reporting(E_ALL);
date_default_timezone_set('UTC');

$loader = require __DIR__ . '/vendor/autoload.php';

// Magento's translation function, normally loaded by app/functions.php.
if (!function_exists('__')) {
    require __DIR__ . '/vendor/magento/framework/Phrase/__.php';
}

// Magento generates <Class>Factory at runtime; without an installation, declare a minimal one so it can be mocked.
// The base class is only located, not loaded: loading it would compile Magento core files the tests never use
// (the product collection triggers a PHP 8.5 cast deprecation in older Magento releases).
spl_autoload_register(static function (string $class) use ($loader): void {
    if (!str_ends_with($class, 'Factory') || $loader->findFile(substr($class, 0, -7)) === false) {
        return;
    }
    $separator = (int)strrpos($class, '\\');
    eval(sprintf(
        'namespace %s; class %s { public function create(array $data = []) { return null; } }',
        substr($class, 0, $separator),
        substr($class, $separator + 1)
    ));
});

// This module (the harness vendor folder was taken from a sibling module).
$loader->addPsr4('Magelan\\SearchCms\\', dirname(__DIR__, 2) . '/');
