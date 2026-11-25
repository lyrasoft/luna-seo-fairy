<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Middleware;

use Lyrasoft\Luna\Services\LocaleService;
use Lyrasoft\SeoFairy\Services\MetaRuleApplier;
use Lyrasoft\SeoFairy\Services\MetaRuleMatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\View\Event\BeforeRenderEvent;

/**
 * Applies matched MetaRules to the HtmlFrame of the rendered view.
 *
 * Mount it on the routes you want, and toggle what to apply with named arguments:
 *
 * ```php
 * ->middleware(MetaRuleMiddleware::class, customCode: false)
 * ```
 */
class MetaRuleMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected AppContext $app,
        protected MetaRuleMatcher $matcher,
        protected MetaRuleApplier $applier,
        protected LocaleService $localeService,
        protected bool $meta = true,
        protected bool $openGraph = true,
        protected bool $jsonLd = true,
        protected bool $customCode = true,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->meta && !$this->openGraph && !$this->jsonLd && !$this->customCode) {
            return $handler->handle($request);
        }

        $result = $this->matcher->resolve($this->getPath(), $this->getLanguage());

        if ($result->isEmpty()) {
            return $handler->handle($request);
        }

        $applied = false;

        // BeforeRenderEvent fires after the view model's prepare(), so "force" can override values set there.
        $this->app->on(
            BeforeRenderEvent::class,
            function (BeforeRenderEvent $event) use ($result, &$applied) {
                if ($applied || $event->view->isChild()) {
                    return;
                }

                $applied = true;

                $this->applier->apply(
                    $event->view->getHtmlFrame(),
                    $result,
                    meta: $this->meta,
                    openGraph: $this->openGraph,
                    jsonLd: $this->jsonLd,
                    customCode: $this->customCode,
                );
            }
        );

        return $handler->handle($request);
    }

    /**
     * Route path without the locale prefix.
     */
    protected function getPath(): string
    {
        $route = $this->app->getState()->get('origin_route');

        if ($route === null) {
            $route = $this->app->getSystemUri()->route();
        }

        return (string) $route;
    }

    protected function getLanguage(): ?string
    {
        return $this->localeService->isEnabled() ? $this->localeService->getLocale() : null;
    }
}
