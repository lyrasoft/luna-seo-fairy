<?php

/**
 * PHPUnit bootstrap for SeoFairy tests, run in host app mode.
 *
 * WINDWALKER_TEST_APP_ROOT points at the host application, whose autoloader and
 * installed packages are used. Migrations come from this package and the database
 * from WINDWALKER_TEST_DB_DSN (MySQL only), which is DROPPED and recreated.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Lyrasoft\SeoFairy\SeoFairyPackage;
use Lyrasoft\SeoFairy\Tests\TestApp;
use Unicorn\UnicornPackage;
use Windwalker\Core\Testing\TestAppRegistry;
use Windwalker\DI\Container;

$package = dirname(__DIR__);
$root = env('WINDWALKER_TEST_APP_ROOT') ?: $package;

if (!preg_match('#^([a-z]:)?[\\\\/]#i', $root)) {
    $root = realpath($package . '/' . $root) ?: $root;
}

if (!defined('WINDWALKER_DEBUG')) {
    define('WINDWALKER_DEBUG', true);
}

if (!defined('WINDWALKER_CACHE')) {
    define('WINDWALKER_CACHE', $root . '/cache');
}

if (!defined('WINDWALKER_VIEWS')) {
    define('WINDWALKER_VIEWS', $root . '/views');
}

if (!defined('WINDWALKER_SOURCE')) {
    define('WINDWALKER_SOURCE', $root . '/src');
}

$init = include $root . '/vendor/windwalker/core/src/Core/Testing/test.init.php';

/** @var Container $container */
/** @var ClassLoader $classLoader */
[$container, $classLoader] = $init(
    root: $root,
    workDir: $root
);

$container->options->lazy = true;

$container->loadParameters([
    'app' => [
        'debug' => true,
    ],
]);
$classLoader->addPsr4('Lyrasoft\SeoFairy\Tests\\', __DIR__ . '/');

TestApp::createInstance(
    container: $container->createChild(),
    config: [
        '@root' => $root,
        '@temp' => $root . '/tmp',
        '@cache' => $root . '/cache',
        '@migrations' => $package . '/resources/migrations',
    ],
);

TestAppRegistry::use(TestApp::$instance);

TestApp::$instance->getContainer()->registerByConfig(
    [
        'providers' => [
            UnicornPackage::class,
            SeoFairyPackage::class,
        ],
    ],
);
