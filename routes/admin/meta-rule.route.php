<?php

declare(strict_types=1);

namespace App\Routes;

use Lyrasoft\SeoFairy\Module\Admin\MetaRule\MetaRuleController;
use Lyrasoft\SeoFairy\Module\Admin\MetaRule\MetaRuleEditView;
use Lyrasoft\SeoFairy\Module\Admin\MetaRule\MetaRuleListView;
use Windwalker\Core\Router\RouteCreator;

/** @var  RouteCreator $router */

$router->group('meta-rule')
    ->extra('menu', ['sidemenu' => 'meta_rule_list'])
    ->register(function (RouteCreator $router) {
        $router->any('meta_rule_list', '/meta-rule/list')
            ->controller(
                MetaRuleController::class,
                post: 'copy',
                put: 'filter',
                patch: 'batch',
            )
            ->view(MetaRuleListView::class);

        $router->any('meta_rule_edit', '/meta-rule/edit[/{id}]')
            ->controller(MetaRuleController::class)
            ->view(MetaRuleEditView::class);
    });
