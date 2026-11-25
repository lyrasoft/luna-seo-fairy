<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Data;

/**
 * Merged result of all MetaRules matched by a path.
 */
class MetaRuleResult
{
    public const string GROUP_META = 'meta';

    public const string GROUP_OPEN_GRAPH = 'open_graph';

    public const string GROUP_JSON_LD = 'json_ld';

    public const string GROUP_CUSTOM_CODE = 'custom_code';

    /**
     * @param  array<string, array<string, MetaRuleField>>  $fields  Grouped by group then key.
     * @param  int[]                                         $ruleIds Matched rule IDs in priority order.
     */
    public function __construct(
        protected array $fields = [],
        public array $ruleIds = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    /**
     * @return  array<string, MetaRuleField>
     */
    public function group(string $group): array
    {
        return $this->fields[$group] ?? [];
    }

    public function get(string $group, string $key): ?MetaRuleField
    {
        return $this->fields[$group][$key] ?? null;
    }

    public function has(string $group, string $key): bool
    {
        return isset($this->fields[$group][$key]);
    }
}
