<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Data;

use Stringable;

/**
 * Unescaped HTML that can be pushed into HtmlFrame custom tags.
 *
 * Only use it with content from trusted editors (MetaRule custom code).
 */
readonly class RawHtmlTag implements Stringable
{
    public function __construct(public string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
