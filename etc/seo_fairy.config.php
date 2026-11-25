<?php

declare(strict_types=1);

namespace App\Config;

use Lyrasoft\SeoFairy\SeoFairyPackage;
use Windwalker\Core\Attributes\ConfigModule;

return #[ConfigModule(name: 'seo_fairy', enabled: true, priority: 100, belongsTo: SeoFairyPackage::class)]
static fn() => [
    'providers' => [
        SeoFairyPackage::class,
    ],
];
