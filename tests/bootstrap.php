<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

$autoloaders = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];

$autoloaded = false;

foreach ($autoloaders as $autoloader) {
    if (!is_readable($autoloader)) {
        continue;
    }

    require_once $autoloader;
    $autoloaded = true;

    break;
}

if (!$autoloaded) {
    throw new RuntimeException('Composer autoloader for tests could not be found.');
}

require_once __DIR__ . '/Support/TestEnvironment.php';
require_once __DIR__ . '/Support/Fixtures.php';

\SymPress\EventDispatcher\Tests\Support\HookState::reset();
