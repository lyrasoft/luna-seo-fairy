<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Enum;

use Windwalker\Utilities\Attributes\Enum\Title;
use Windwalker\Utilities\Contract\LanguageInterface;
use Windwalker\Utilities\Enum\EnumRichInterface;
use Windwalker\Utilities\Enum\EnumRichTrait;

/**
 * How a MetaRule treats values that the page has already set.
 */
enum MetaRuleOverrideMode: string implements EnumRichInterface
{
    use EnumRichTrait;

    #[Title(string: 'Fallback', lang: 'seo.fairy.meta.rule.override.mode.fallback')]
    case FALLBACK = 'fallback';

    #[Title(string: 'Force', lang: 'seo.fairy.meta.rule.override.mode.force')]
    case FORCE = 'force';

    public static function preprocessValue(mixed $value): mixed
    {
        return $value ?: self::FALLBACK;
    }

    public function getDescription(LanguageInterface $lang): string
    {
        return $lang->trans('seo.fairy.meta.rule.override.mode.' . $this->value . '.desc');
    }
}
