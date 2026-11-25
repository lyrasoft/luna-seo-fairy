<?php

declare(strict_types=1);

namespace Lyrasoft\SeoFairy\Services;

use Lyrasoft\SeoFairy\Data\MetaRuleField;
use Lyrasoft\SeoFairy\Data\MetaRuleResult;
use Lyrasoft\SeoFairy\Data\RawHtmlTag;
use Windwalker\Core\Html\HtmlFrame;

/**
 * Applies a merged MetaRuleResult to the HtmlFrame.
 */
class MetaRuleApplier
{
    protected const string JSON_LD_TYPE = 'application/ld+json';

    protected const array META_NAMES = [
        'description' => 'description',
        'keywords' => 'keywords',
        'robots' => 'robots',
    ];

    protected const array OG_TYPES = [
        'ogTitle' => 'og:title',
        'ogDescription' => 'og:description',
    ];

    public function apply(
        HtmlFrame $htmlFrame,
        MetaRuleResult $result,
        bool $meta = true,
        bool $openGraph = true,
        bool $jsonLd = true,
        bool $customCode = true,
    ): void {
        if ($meta) {
            $this->applyMeta($htmlFrame, $result);
        }

        if ($openGraph) {
            $this->applyOpenGraph($htmlFrame, $result);
        }

        if ($jsonLd) {
            $this->applyJsonLd($htmlFrame, $result);
        }

        if ($customCode) {
            $this->applyCustomCode($htmlFrame, $result);
        }
    }

    protected function applyMeta(HtmlFrame $htmlFrame, MetaRuleResult $result): void
    {
        foreach ($result->group(MetaRuleResult::GROUP_META) as $key => $field) {
            if ($key === 'title') {
                if ($field->isForce() || (string) $htmlFrame->getTitle() === '') {
                    $htmlFrame->setTitle($field->value);
                }

                continue;
            }

            $name = static::META_NAMES[$key] ?? null;

            if ($name === null) {
                continue;
            }

            if ($field->isForce() || !$this->hasMetadata($htmlFrame, $name)) {
                $htmlFrame->addMetadata($name, $field->value, true);
            }
        }
    }

    protected function applyOpenGraph(HtmlFrame $htmlFrame, MetaRuleResult $result): void
    {
        foreach ($result->group(MetaRuleResult::GROUP_OPEN_GRAPH) as $key => $field) {
            if ($key === 'ogImage') {
                if ($field->isForce() || !$this->hasOpenGraph($htmlFrame, 'og:image')) {
                    $htmlFrame->setCoverImages($field->value);
                }

                continue;
            }

            $type = static::OG_TYPES[$key] ?? null;

            if ($type === null) {
                continue;
            }

            if ($field->isForce() || !$this->hasOpenGraph($htmlFrame, $type)) {
                $htmlFrame->addOpenGraph($type, $field->value, true);
            }
        }
    }

    protected function applyJsonLd(HtmlFrame $htmlFrame, MetaRuleResult $result): void
    {
        $field = $result->get(MetaRuleResult::GROUP_JSON_LD, 'json_ld');

        if (!$field) {
            return;
        }

        $json = $this->encodeJsonLd($field);

        if ($json === null) {
            return;
        }

        $exists = array_filter(
            $htmlFrame->getCustomTags(),
            static fn($tag) => str_contains((string) $tag, static::JSON_LD_TYPE)
        );

        if ($exists !== []) {
            if (!$field->isForce()) {
                return;
            }

            $htmlFrame->setCustomTags(array_values(array_diff_key($htmlFrame->getCustomTags(), $exists)));
        }

        $htmlFrame->setCustomTags([
            ...$htmlFrame->getCustomTags(),
            new RawHtmlTag('<script type="' . static::JSON_LD_TYPE . '">' . $json . '</script>'),
        ]);
    }

    protected function applyCustomCode(HtmlFrame $htmlFrame, MetaRuleResult $result): void
    {
        foreach ($result->group(MetaRuleResult::GROUP_CUSTOM_CODE) as $field) {
            $htmlFrame->setCustomTags([
                ...$htmlFrame->getCustomTags(),
                new RawHtmlTag($field->value),
            ]);
        }
    }

    /**
     * Re-encode to validate the JSON and escape "<", ">" and "&" so it can't close the script tag.
     */
    protected function encodeJsonLd(MetaRuleField $field): ?string
    {
        $data = json_decode($field->value, true);

        if (!is_array($data)) {
            return null;
        }

        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        ) ?: null;
    }

    protected function hasMetadata(HtmlFrame $htmlFrame, string $name): bool
    {
        return !empty($htmlFrame->getMetadata()->getMetadata()[$name] ?? []);
    }

    protected function hasOpenGraph(HtmlFrame $htmlFrame, string $type): bool
    {
        return !empty($htmlFrame->getMetadata()->getOpenGraphs()[$type] ?? []);
    }
}
