<?php

/**
 * Plugin Name: Event Dispatcher
 * Description: Symfony-style class-based event dispatcher for native WordPress hooks and filters.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.5
 * Author: Brian Schaeffner
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SymPress\EventDispatcher;

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists(EventDispatcherBundle::class)) {
    require_once __DIR__ . '/vendor/autoload.php';
}
