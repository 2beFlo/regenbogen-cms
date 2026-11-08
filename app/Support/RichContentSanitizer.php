<?php

namespace App\Support;

use Illuminate\Support\Str;

class RichContentSanitizer
{
    public static function sanitize(string $html): string
    {
        $iframes = [];

        // Copying long embed URLs from Matomo/twingle can introduce line
        // breaks inside src="...". Extract iframe blocks first, normalize
        // only their URLs, and put back a strictly allow-listed element after
        // the general sanitizer has processed the surrounding HTML.
        $normalized = preg_replace_callback(
            '/<iframe\b([^>]*)>(?:.*?<\/iframe\s*>)?/is',
            static function (array $match) use (&$iframes): string {
                $safeIframe = self::safeIframe($match[1]);

                if ($safeIframe === null) {
                    return '';
                }

                $token = 'REGENBOGEN_IFRAME_'.count($iframes).'_END';
                $iframes[$token] = $safeIframe;

                return '<p>'.$token.'</p>';
            },
            $html,
        ) ?? $html;

        $sanitized = (string) Str::of($normalized)->sanitizeHtml();

        return ResponsiveImages::enhanceHtml(strtr($sanitized, $iframes));
    }

    private static function safeIframe(string $attributes): ?string
    {
        preg_match_all('/([a-zA-Z][a-zA-Z0-9:-]*)\s*=\s*(["\'])(.*?)\2/is', $attributes, $matches, PREG_SET_ORDER);

        $values = [];
        foreach ($matches as $match) {
            $values[strtolower($match[1])] = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (! isset($values['src'])) {
            return null;
        }

        $src = preg_replace('/\s+/', '', trim($values['src'])) ?? '';
        $parts = parse_url($src);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $isRelative = $src !== '' && ($src[0] === '/' || $scheme === '');

        if ($src === '' || (! $isRelative && ! in_array($scheme, ['http', 'https'], true))) {
            return null;
        }

        $allowed = ['src', 'title', 'width', 'height', 'loading', 'referrerpolicy', 'allow', 'sandbox', 'class', 'scrolling', 'frameborder'];
        $output = [];

        foreach ($allowed as $name) {
            if (! array_key_exists($name, $values)) {
                continue;
            }

            $value = trim($values[$name]);

            if ($name === 'src') {
                $value = $src;
            }

            if (in_array($name, ['width', 'height', 'frameborder'], true) && ! preg_match('/^[0-9]+(?:px|%)?$/i', $value)) {
                continue;
            }

            $output[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8').'"';
        }

        if (preg_match('/\ballowfullscreen\b/i', $attributes)) {
            $output[] = 'allowfullscreen="allowfullscreen"';
        }

        if (isset($values['style'])) {
            $styleParts = [];
            preg_match_all('/(?:^|;)\s*(border|height|width)\s*:\s*([0-9.]+(?:px|%|rem|em|vh|vw)?|none|auto)\s*(?:;|$)/i', $values['style'], $styleMatches, PREG_SET_ORDER);

            foreach ($styleMatches as $styleMatch) {
                $styleParts[] = strtolower($styleMatch[1]).':'.$styleMatch[2];
            }

            if ($styleParts !== []) {
                $output[] = 'style="'.htmlspecialchars(implode(';', $styleParts), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8').'"';
            }
        }

        return '<iframe '.implode(' ', $output).'></iframe>';
    }
}
