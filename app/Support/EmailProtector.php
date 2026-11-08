<?php

namespace App\Support;

class EmailProtector
{
    private const EMAIL_PATTERN = '/(?<![\w.+-])([A-Z0-9._%+\-]+)@([A-Z0-9.\-]+\.[A-Z]{2,})(?![\w.-])/i';

    public static function protectPlainText(?string $text): string
    {
        $escaped = e((string) $text);
        $protected = preg_replace_callback(self::EMAIL_PATTERN, static function (array $match): string {
            return self::placeholder($match[1].'@'.$match[2], false);
        }, $escaped) ?? $escaped;

        return nl2br($protected);
    }

    public static function protectLink(?string $url, ?string $label = null): string
    {
        $url = trim((string) $url);
        $label = trim((string) ($label ?: $url));

        if (str_starts_with(strtolower($url), 'mailto:')) {
            $email = preg_replace('/\?.*$/', '', substr($url, 7)) ?: '';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return self::placeholder($email, true, $label);
            }
        }

        return '<a href="'.e($url).'">'.e($label).'</a>';
    }

    public static function protectHtml(?string $html): string
    {
        $html = (string) $html;
        if ($html === '' || ! str_contains($html, '@')) {
            return $html;
        }

        // Replace complete mailto links first so neither href nor visible address
        // remains in the server-rendered HTML source.
        $html = preg_replace_callback(
            '~<a\b([^>]*?)href=([\'"])mailto:([^"\'\s>?]+)(?:\?[^"\']*)?\2([^>]*)>(.*?)</a>~is',
            static function (array $match): string {
                $email = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $match[0];
                }

                $label = trim(strip_tags($match[5]));

                return self::placeholder($email, true, preg_match(self::EMAIL_PATTERN, $label) ? null : $label);
            },
            $html,
        ) ?? $html;

        // Only touch text nodes. Splitting on tags avoids rewriting attributes,
        // URLs and other markup while still protecting addresses typed directly
        // into articles, pages, imprint text, etc.
        $parts = preg_split('/(<[^>]+>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        foreach ($parts as $index => $part) {
            if ($part === '' || str_starts_with($part, '<')) {
                continue;
            }

            $parts[$index] = preg_replace_callback(self::EMAIL_PATTERN, static function (array $match): string {
                return self::placeholder(html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }, $part) ?? $part;
        }

        return implode('', $parts);
    }

    private static function placeholder(string $email, bool $link = false, ?string $label = null): string
    {
        $encoded = e(self::encode($email));
        $visible = 'E-Mail-Adresse wird geladen …';
        $labelAttribute = $label && $label !== $email ? ' data-email-label="'.e($label).'"' : '';

        if ($link) {
            return '<a href="#" class="protected-email" data-protected-email="'.$encoded.'" data-email-link="1"'.$labelAttribute.' rel="nofollow">'.$visible.'</a>';
        }

        return '<span class="protected-email" data-protected-email="'.$encoded.'"'.$labelAttribute.'>'.$visible.'</span>';
    }

    private static function encode(string $email): string
    {
        return rtrim(strtr(base64_encode(strrev($email)), '+/', '-_'), '=');
    }
}
