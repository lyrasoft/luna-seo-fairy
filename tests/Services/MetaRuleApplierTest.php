<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Tests\Services;

use Lyrasoft\SeoFairy\Entity\MetaRule;
use Lyrasoft\SeoFairy\Enum\MetaRuleOverrideMode;
use Lyrasoft\SeoFairy\Services\MetaRuleApplier;
use Lyrasoft\SeoFairy\Services\MetaRuleMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Unicorn\Enum\BasicState;
use Windwalker\Core\Asset\AssetService;
use Windwalker\Core\Html\HtmlFrame;
use Windwalker\ORM\ORM;

class MetaRuleApplierTest extends TestCase
{
    private function frame(): HtmlFrame
    {
        $asset = $this->createStub(AssetService::class);
        $asset->method('addAssetBase')->willReturnArgument(0);

        return new HtmlFrame($asset);
    }

    private function rule(int $id, MetaRuleOverrideMode $mode, array $meta = [], string $json = '', array $codes = []): MetaRule
    {
        $rule = new MetaRule();
        $rule->id = $id;
        $rule->overrideMode = $mode;
        $rule->meta = $meta;
        $rule->jsonLd = $json;
        $rule->customCodes = $codes;
        $rule->state = BasicState::PUBLISHED;

        return $rule;
    }

    private function resolve(MetaRule ...$rules)
    {
        return (new MetaRuleMatcher($this->createStub(ORM::class)))->merge($rules);
    }

    #[Test]
    public function forceOverridesAndFallbackOnlyFills(): void
    {
        $frame = $this->frame();
        $frame->setTitle('Page');
        $frame->addMetadata('description', 'Page desc');
        $frame->addOpenGraph('og:title', 'Page OG');

        $applier = new MetaRuleApplier();

        $applier->apply($frame, $this->resolve($this->rule(1, MetaRuleOverrideMode::FORCE, [
            'title' => 'Forced',
            'keywords' => 'a,b',
            'ogTitle' => 'OG Forced',
        ])));

        self::assertSame('Forced', $frame->getTitle());
        self::assertStringContainsString('content="a,b"', $frame->renderMetadata());
        self::assertStringContainsString('content="OG Forced"', $frame->renderMetadata());
        self::assertStringNotContainsString('Page OG', $frame->renderMetadata());

        $frame = $this->frame();
        $frame->setTitle('Page');
        $frame->addMetadata('description', 'Page desc');

        $applier->apply($frame, $this->resolve($this->rule(1, MetaRuleOverrideMode::FALLBACK, [
            'title' => 'Fallback',
            'description' => 'Rule desc',
            'robots' => 'noindex',
        ])));

        self::assertSame('Page', $frame->getTitle());
        self::assertStringContainsString('Page desc', $frame->renderMetadata());
        self::assertStringNotContainsString('Rule desc', $frame->renderMetadata());
        self::assertStringContainsString('noindex', $frame->renderMetadata());
    }

    #[Test]
    public function optionsToggleEachGroup(): void
    {
        $result = $this->resolve($this->rule(
            1,
            MetaRuleOverrideMode::FORCE,
            ['title' => 'T', 'ogTitle' => 'OG'],
            '{"@type":"Thing"}',
            ['head' => '<script>x()</script>'],
        ));

        $applier = new MetaRuleApplier();

        $frame = $this->frame();
        $applier->apply($frame, $result, meta: false, openGraph: false, jsonLd: false, customCode: false);
        self::assertNull($frame->getTitle());
        self::assertSame('', $frame->renderMetadata());
        self::assertSame('', $frame->renderCustomTags());

        $frame = $this->frame();
        $applier->apply($frame, $result, meta: true, openGraph: false, jsonLd: false, customCode: false);
        self::assertSame('T', $frame->getTitle());
        self::assertSame('', $frame->renderMetadata());

        $frame = $this->frame();
        $applier->apply($frame, $result, meta: false, openGraph: true, jsonLd: false, customCode: false);
        self::assertStringContainsString('og:title', $frame->renderMetadata());

        $frame = $this->frame();
        $applier->apply($frame, $result, meta: false, openGraph: false, jsonLd: true, customCode: false);
        self::assertStringContainsString('application/ld+json', $frame->renderCustomTags());
        self::assertStringNotContainsString('x()', $frame->renderCustomTags());

        $frame = $this->frame();
        $applier->apply($frame, $result, meta: false, openGraph: false, jsonLd: false, customCode: true);
        self::assertStringContainsString('<script>x()</script>', $frame->renderCustomTags());
    }

    #[Test]
    public function jsonLdIsEscapedValidatedAndModeAware(): void
    {
        $applier = new MetaRuleApplier();

        $frame = $this->frame();
        $applier->apply($frame, $this->resolve($this->rule(
            1,
            MetaRuleOverrideMode::FORCE,
            json: '{"name":"</script><script>alert(1)</script>"}',
        )));
        $html = $frame->renderCustomTags();
        self::assertSame(1, substr_count($html, '</script>'));
        self::assertStringContainsString('\u003C/script\u003E', $html);

        $frame = $this->frame();
        $applier->apply($frame, $this->resolve($this->rule(1, MetaRuleOverrideMode::FORCE, json: 'not json')));
        self::assertSame('', $frame->renderCustomTags());

        $frame = $this->frame();
        $applier->apply($frame, $this->resolve($this->rule(1, MetaRuleOverrideMode::FORCE, json: '{"a":1}')));
        $applier->apply($frame, $this->resolve($this->rule(2, MetaRuleOverrideMode::FALLBACK, json: '{"b":2}')));
        self::assertSame(1, substr_count($frame->renderCustomTags(), 'ld+json'));
        self::assertStringContainsString('"a":1', $frame->renderCustomTags());

        $applier->apply($frame, $this->resolve($this->rule(3, MetaRuleOverrideMode::FORCE, json: '{"c":3}')));
        self::assertSame(1, substr_count($frame->renderCustomTags(), 'ld+json'));
        self::assertStringContainsString('"c":3', $frame->renderCustomTags());
    }
}
