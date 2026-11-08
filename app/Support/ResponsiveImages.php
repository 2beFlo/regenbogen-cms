<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ResponsiveImages
{
    public static function isOptimizable(?string $path): bool
    {
        return $path !== null
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)
            && Storage::disk('uploads')->exists($path);
    }

    public static function url(string $path, int $width): string
    {
        return route('images.variant', ['path' => $path, 'width' => $width]);
    }

    public static function srcset(string $path, array $widths): string
    {
        return collect($widths)
            ->unique()
            ->sort()
            ->map(fn (int $width): string => self::url($path, $width).' '.$width.'w')
            ->implode(', ');
    }

    public static function dimensions(string $path): ?array
    {
        if (! self::isOptimizable($path)) {
            return null;
        }

        $dimensions = @getimagesize(Storage::disk('uploads')->path($path));

        return is_array($dimensions) && isset($dimensions[0], $dimensions[1])
            ? ['width' => (int) $dimensions[0], 'height' => (int) $dimensions[1]]
            : null;
    }

    public static function enhanceHtml(string $html): string
    {
        return preg_replace_callback('/<img\b([^>]*)>/i', static function (array $match): string {
            $attributes = $match[1];
            if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attributes, $srcMatch) !== 1) {
                return $match[0];
            }

            $src = html_entity_decode($srcMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $path = parse_url($src, PHP_URL_PATH) ?: $src;
            $prefix = '/uploads/';
            if (! str_starts_with($path, $prefix)) {
                return $match[0];
            }

            $relative = ltrim(substr($path, strlen($prefix)), '/');
            if (! self::isOptimizable($relative)) {
                return $match[0];
            }

            if (! str_contains(strtolower($attributes), 'srcset=')) {
                $attributes .= ' srcset="'.htmlspecialchars(self::srcset($relative, [480, 800, 1200, 1600]), ENT_QUOTES | ENT_HTML5, 'UTF-8').'" sizes="(max-width: 46rem) 100vw, 56rem"';
            }

            if (! preg_match('/\bloading\s*=/i', $attributes)) {
                $attributes .= ' loading="lazy"';
            }

            if (! preg_match('/\bdecoding\s*=/i', $attributes)) {
                $attributes .= ' decoding="async"';
            }

            $dimensions = self::dimensions($relative);
            if ($dimensions && ! preg_match('/\bwidth\s*=/i', $attributes)) {
                $attributes .= ' width="'.$dimensions['width'].'" height="'.$dimensions['height'].'"';
            }

            return '<img'.$attributes.'>';
        }, $html) ?? $html;
    }
}
