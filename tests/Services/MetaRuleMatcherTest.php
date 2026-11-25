<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Tests\Services;

use Lyrasoft\SeoFairy\Data\MetaRuleResult;
use Lyrasoft\SeoFairy\Entity\MetaRule;
use Lyrasoft\SeoFairy\Enum\MetaRuleOverrideMode;
use Lyrasoft\SeoFairy\Services\MetaRuleMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Unicorn\Enum\BasicState;
use Windwalker\Core\Testing\Database\TestDatabaseFixtures;
use Windwalker\Core\Testing\Traits\DatabaseTestTrait;

class MetaRuleMatcherTest extends TestCase
{
    use DatabaseTestTrait;

    protected static function prepareTablesBeforeClass(): void
    {
        TestDatabaseFixtures::resetAndMigrate();
    }

    #[Test]
    #[DataProvider('globProvider')]
    public function globMatch(string $pattern, string $path, bool $expected): void
    {
        self::assertSame($expected, MetaRuleMatcher::globMatch($pattern, $path));
    }

    public static function globProvider(): array
    {
        return [
            'root' => ['/', '/', true],
            'root not child' => ['/', '/a', false],
            'exact' => ['/login', '/login', true],
            'trailing slash normalized' => ['/login', '/login/', true],
            'exact no child' => ['/login', '/login/x', false],
            'single star in segment' => ['/article/*', '/article/12-foo', true],
            'single star not crossing' => ['/article/*', '/article/12/foo', false],
            'globstar crosses' => ['/articles/**', '/articles/a/b/c', true],
            'globstar matches parent' => ['/articles/**', '/articles', true],
            'globstar not sibling' => ['/articles/**', '/articlesx', false],
            'all' => ['/**', '/any/thing', true],
            'all root' => ['/**', '/', true],
            'question mark' => ['/a?c', '/abc', true],
            'brace' => ['/{login,registration}', '/registration', true],
            'brace miss' => ['/{login,registration}', '/forget', false],
            'class' => ['/v[12]/x', '/v2/x', true],
            'class miss' => ['/v[12]/x', '/v3/x', false],
            'empty pattern' => ['', '/', false],
            'regex chars literal' => ['/a.b', '/axb', false],
        ];
    }

    #[Test]
    public function excludePatternsBlockRule(): void
    {
        $rule = $this->rule(1, '/products/**', exclude: "/products/export\n\n  /products/draft/** ");

        self::assertTrue(MetaRuleMatcher::ruleMatches($rule, '/products/a'));
        self::assertFalse(MetaRuleMatcher::ruleMatches($rule, '/products/export'));
        self::assertFalse(MetaRuleMatcher::ruleMatches($rule, '/products/draft/x'));
    }

    #[Test]
    public function mergePicksFirstProvidedValuePerFieldWithOwnMode(): void
    {
        $matcher = new MetaRuleMatcher(self::$orm);

        $specific = $this->rule(1, '/a/**', ['title' => 'Specific'], MetaRuleOverrideMode::FALLBACK);
        $general = $this->rule(
            2,
            '/**',
            ['title' => 'General', 'description' => 'General desc', 'ogTitle' => 'OG'],
            MetaRuleOverrideMode::FORCE,
            json: '{"@type":"Thing"}',
            codes: ['head' => '<meta x>'],
        );

        $result = $matcher->merge([$specific, $general]);

        self::assertSame('Specific', $result->get(MetaRuleResult::GROUP_META, 'title')->value);
        self::assertFalse($result->get(MetaRuleResult::GROUP_META, 'title')->isForce());
        self::assertSame('General desc', $result->get(MetaRuleResult::GROUP_META, 'description')->value);
        self::assertTrue($result->get(MetaRuleResult::GROUP_META, 'description')->isForce());
        self::assertSame('OG', $result->get(MetaRuleResult::GROUP_OPEN_GRAPH, 'ogTitle')->value);
        self::assertSame('{"@type":"Thing"}', $result->get(MetaRuleResult::GROUP_JSON_LD, 'json_ld')->value);
        self::assertSame('<meta x>', $result->get(MetaRuleResult::GROUP_CUSTOM_CODE, 'head')->value);
        self::assertFalse($result->has(MetaRuleResult::GROUP_CUSTOM_CODE, 'body'));
        self::assertSame([1, 2], $result->ruleIds);
    }

    #[Test]
    public function matchUsesDatabaseOrderingStateAndLanguage(): void
    {
        self::truncateTables(self::$orm->getEntityMetadata(MetaRule::class)->getTableName());

        $this->store('General', '/**', 3, ['title' => 'General']);
        $this->store('Article', '/article/*', 1, ['title' => 'Article']);
        $this->store('Off', '/article/*', 2, ['title' => 'Off'], BasicState::UNPUBLISHED);
        $this->store('Zh', '/article/*', 0, ['title' => 'Zh'], language: 'zh-TW');
        $this->store('Excluded', '/article/*', 4, ['title' => 'Ex'], exclude: '/article/9-*');

        $matcher = new MetaRuleMatcher(self::$orm);

        $titles = static fn(array $rules) => array_map(static fn(MetaRule $r) => $r->title, $rules);

        self::assertSame(['Article', 'General', 'Excluded'], $titles($matcher->match('/article/1-a?x=1', 'en-US')));
        self::assertSame(['Zh', 'Article', 'General', 'Excluded'], $titles($matcher->match('/article/1-a', 'zh-TW')));
        self::assertSame(['Article', 'General'], $titles($matcher->match('/article/9-a')));
        self::assertSame('Article', $matcher->resolve('/article/1-a')->get('meta', 'title')->value);
    }

    private function rule(
        int $id,
        string $pattern,
        array $meta = [],
        MetaRuleOverrideMode $mode = MetaRuleOverrideMode::FALLBACK,
        string $exclude = '',
        string $json = '',
        array $codes = [],
    ): MetaRule {
        $rule = new MetaRule();
        $rule->id = $id;
        $rule->pattern = $pattern;
        $rule->excludePattern = $exclude;
        $rule->overrideMode = $mode;
        $rule->meta = $meta;
        $rule->jsonLd = $json;
        $rule->customCodes = $codes;
        $rule->state = BasicState::PUBLISHED;

        return $rule;
    }

    private function store(
        string $title,
        string $pattern,
        int $ordering,
        array $meta,
        BasicState $state = BasicState::PUBLISHED,
        string $language = '',
        string $exclude = '',
    ): void {
        $rule = $this->rule(0, $pattern, $meta, exclude: $exclude);
        $rule->id = null;
        $rule->title = $title;
        $rule->ordering = $ordering;
        $rule->state = $state;
        $rule->language = $language;

        self::$orm->createOne(MetaRule::class, $rule);
    }
}
