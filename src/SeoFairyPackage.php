<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy;

use Windwalker\Core\Package\AbstractPackage;
use Windwalker\Core\Package\PackageInstaller;
use Windwalker\DI\Container;
use Windwalker\DI\ServiceProviderInterface;

class SeoFairyPackage extends AbstractPackage implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        //
    }

    public function install(PackageInstaller $installer): void
    {
        $installer->installConfig(static::path('etc/*.php'), 'config');
    }
}
