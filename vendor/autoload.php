<?php
/**
 * Custom Autoloader for DFS Abonnements
 * Acts as a drop-in replacement for Composer autoloader
 * to ensure PrestaShop loads the classes during Symfony container compilation.
 */

spl_autoload_register(function ($class) {
    $prefix = 'DfsAbonnements\\';
    $baseDir = dirname(__DIR__) . '/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});