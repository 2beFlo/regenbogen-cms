<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SeoMetadata
{
    public static function robots(SiteSetting $settings, ?string $override = null): string
    {
        $robots = $override ?: ($settings->seo_default_robots ?: 'index,follow');

        return in_array($robots, ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'], true)
            ? $robots
            : 'index,follow';
    }

    public static function isIndexable(SiteSetting $settings, ?Model $record = null, ?string $robotsOverride = null): bool
    {
        return str_starts_with(self::robots($settings, $robotsOverride ?? $record?->seo_robots), 'index,');
    }

    public static function siteUrl(SiteSetting $settings): string
    {
        return rtrim((string) ($settings->seo_site_url ?: 'https://regenbogen-duderstadt.de'), '/');
    }

    public static function canonical(SiteSetting $settings, string $path, ?string $override = null): string
    {
        if (filled($override)) {
            return trim($override);
        }

        $path = '/'.ltrim($path, '/');

        return self::siteUrl($settings).($path === '/' ? '/' : $path);
    }

    public static function onSiteCanonical(SiteSetting $settings, string $url): bool
    {
        $canonicalHost = parse_url(self::siteUrl($settings), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        return is_string($canonicalHost) && is_string($urlHost) && strcasecmp($canonicalHost, $urlHost) === 0;
    }

    public static function imageUrl(SiteSetting $settings, ?string $path, bool $publisher = false): string
    {
        if (filled($path)) {
            if (preg_match('#^https?://#i', $path)) {
                return $path;
            }

            return Storage::disk('uploads')->url($path);
        }

        if ($publisher) {
            if (filled($settings->seo_publisher_logo)) {
                return self::imageUrl($settings, $settings->seo_publisher_logo);
            }

            return self::siteUrl($settings).'/assets/regenbogen-mark.png';
        }

        if (filled($settings->seo_default_og_image)) {
            return self::imageUrl($settings, $settings->seo_default_og_image);
        }

        return self::siteUrl($settings).'/assets/regenbogen-mark.png';
    }

    /** @return array{title:string,description:string,robots:string,canonical:string,og_title:string,og_description:string,og_image:string} */
    public static function resolve(
        SiteSetting $settings,
        string $path,
        ?Model $record = null,
        ?string $defaultTitle = null,
        ?string $defaultDescription = null,
        ?string $defaultImage = null,
        ?string $robotsOverride = null,
        ?string $titleOverride = null,
        ?string $descriptionOverride = null,
        ?string $canonicalOverride = null,
        ?string $ogTitleOverride = null,
        ?string $ogDescriptionOverride = null,
        ?string $ogImageOverride = null,
    ): array {
        $title = trim((string) ($record?->seo_title ?: $titleOverride ?: $defaultTitle ?: $settings->site_title));
        $suffix = trim((string) ($settings->seo_title_suffix ?: ''));
        if ($suffix !== '' && ! str_ends_with(mb_strtolower($title), mb_strtolower($suffix))) {
            $title .= ' – '.$suffix;
        }

        $description = trim((string) ($record?->seo_description ?: $descriptionOverride ?: $defaultDescription ?: $settings->seo_default_description));
        if ($description === '') {
            $description = 'Das Magazin der Grünen Untereichsfeld: aktuelle Artikel und Ausgaben aus Duderstadt und dem Untereichsfeld.';
        }

        $robots = self::robots($settings, $robotsOverride ?? $record?->seo_robots);
        $canonical = self::canonical($settings, $path, $canonicalOverride ?? $record?->seo_canonical_url);
        $ogTitle = trim((string) ($record?->og_title ?: $ogTitleOverride ?: $title));
        $ogDescription = trim((string) ($record?->og_description ?: $ogDescriptionOverride ?: $description));
        $ogImage = self::imageUrl($settings, $record?->og_image ?: $ogImageOverride ?: $defaultImage);

        return compact('title', 'description', 'robots', 'canonical') + [
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image' => $ogImage,
        ];
    }
}
