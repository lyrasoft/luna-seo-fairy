<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Services;

use Lyrasoft\SeoFairy\Data\MetaRuleField;
use Lyrasoft\SeoFairy\Data\MetaRuleResult;
use Lyrasoft\SeoFairy\Entity\MetaRule;
use Unicorn\Enum\BasicState;
use Webmozart\Glob\Glob;
use Windwalker\ORM\ORM;

/**
 * Matches request paths against MetaRules and merges the matched rules field by field.
 */
class MetaRuleMatcher
{
    /**
     * @var  string[]  Meta keys exposed by the "meta" group.
     */
    protected const array META_KEYS = ['title', 'description', 'keywords', 'robots'];

    /**
     * @var  string[]  Meta keys exposed by the "open_graph" group.
     */
    protected const array OG_KEYS = ['ogTitle', 'ogDescription', 'ogImage'];

    /**
     * @var  MetaRule[]|null
     */
    protected ?array $rules = null;

    public function __construct(protected ORM $orm) {}

    /**
     * Match and merge in one step.
     */
    public function resolve(string $path, ?string $language = null): MetaRuleResult
    {
        return $this->merge($this->match($path, $language));
    }

    /**
     * Get rules matching the path, ordered by priority (smaller ordering first).
     *
     * @return  MetaRule[]
     */
    public function match(string $path, ?string $language = null): array
    {
        // Drop query string and fragment; "?" is a glob char so patterns are not stripped.
        $path = static::normalizePath((string) strtok($path, '?#'));
        $matched = [];

        foreach ($this->getActiveRules() as $rule) {
            if (!static::languageMatches($rule->language, $language)) {
                continue;
            }

            if (!static::ruleMatches($rule, $path)) {
                continue;
            }

            $matched[] = $rule;
        }

        return $matched;
    }

    /**
     * Pick, for every field, the first rule (by priority) that provides a value.
     * Each field keeps the override mode of its own winning rule.
     *
     * @param  MetaRule[]  $rules  Must already be sorted by priority.
     */
    public function merge(array $rules): MetaRuleResult
    {
        $fields = [];
        $ruleIds = [];

        foreach ($rules as $rule) {
            $ruleIds[] = $rule->id;

            $put = static function (string $group, string $key, string $value) use (&$fields, $rule): void {
                if (trim($value) === '' || isset($fields[$group][$key])) {
                    return;
                }

                $fields[$group][$key] = new MetaRuleField($group, $key, $value, $rule->overrideMode, $rule->id);
            };

            foreach (static::META_KEYS as $key) {
                $put(MetaRuleResult::GROUP_META, $key, (string) $rule->meta->$key);
            }

            foreach (static::OG_KEYS as $key) {
                $put(MetaRuleResult::GROUP_OPEN_GRAPH, $key, (string) $rule->meta->$key);
            }

            $put(MetaRuleResult::GROUP_JSON_LD, 'json_ld', $rule->jsonLd);

            foreach (['head', 'body'] as $position) {
                $put(MetaRuleResult::GROUP_CUSTOM_CODE, $position, (string) ($rule->customCodes[$position] ?? ''));
            }
        }

        return new MetaRuleResult($fields, $ruleIds);
    }

    /**
     * @return  MetaRule[]
     */
    public function getActiveRules(): array
    {
        return $this->rules ??= $this->orm->from(MetaRule::class)
            ->where('state', BasicState::PUBLISHED)
            ->order('ordering', \SortDirection::Ascending)
            ->order('id', \SortDirection::Ascending)
            ->all(MetaRule::class)
            ->dump();
    }

    public function clearCache(): void
    {
        $this->rules = null;
    }

    public static function ruleMatches(MetaRule $rule, string $path): bool
    {
        $path = static::normalizePath($path);

        if (!static::globMatch($rule->pattern, $path)) {
            return false;
        }

        foreach (static::splitPatterns($rule->excludePattern) as $exclude) {
            if (static::globMatch($exclude, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return  string[]
     */
    public static function splitPatterns(string $patterns): array
    {
        $lines = preg_split('/\R/', $patterns) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn(string $line) => $line !== ''));
    }

    /**
     * Ensure leading slash, collapse duplicate slashes and drop the trailing slash (except root).
     */
    public static function normalizePath(string $path): string
    {
        $path = '/' . preg_replace('#/+#', '/', trim($path, '/'));

        return $path === '' ? '/' : $path;
    }

    /**
     * Glob match powered by Webmozart Glob: `*` stays within a segment, `/**` crosses segments
     * (a trailing `/**` also matches the bare parent), `?`, `[abc]` and `{a,b}` are supported.
     */
    public static function globMatch(string $pattern, string $path): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        $pattern = static::normalizePath($pattern);
        $path = static::normalizePath($path);

        // Webmozart treats a trailing "/**" like "/*", so expand it to "/**/*" and also accept the bare parent.
        if (str_ends_with($pattern, '/**')) {
            $base = substr($pattern, 0, -3);

            return $path === ($base === '' ? '/' : $base) || Glob::match($path, $base . '/**/*');
        }

        return Glob::match($path, $pattern);
    }

    public static function languageMatches(?string $ruleLanguage, ?string $language): bool
    {
        if ($ruleLanguage === null || $ruleLanguage === '' || $ruleLanguage === '*') {
            return true;
        }

        return $language !== null && strcasecmp($ruleLanguage, $language) === 0;
    }
}
