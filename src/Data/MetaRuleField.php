<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Data;

use Lyrasoft\SeoFairy\Enum\MetaRuleOverrideMode;

/**
 * A single field value resolved from the winning MetaRule.
 */
readonly class MetaRuleField
{
    public function __construct(
        public string $group,
        public string $key,
        public string $value,
        public MetaRuleOverrideMode $overrideMode,
        public ?int $ruleId = null,
    ) {
    }

    public function isForce(): bool
    {
        return $this->overrideMode === MetaRuleOverrideMode::FORCE;
    }
}
