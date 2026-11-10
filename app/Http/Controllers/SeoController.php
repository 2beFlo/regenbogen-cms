<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CmsPage;
use App\Models\Issue;
use App\Models\SiteSetting;
use App\Support\SeoMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

class SeoController
{
    public function robots(): Response
    {
        $settings = SiteSetting::current();
        $siteUrl = SeoMetadata::siteUrl($settings);
        $body = implode("\n", [
            'User-agent: *',
            'Sitemap: '.$siteUrl.'/sitemap.xml',
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $settings = SiteSetting::current();
        $urls = collect();
        $add = static function (string $path, ?Model $record = null, ?string $robots = null, $updatedAt = null, ?string $canonicalOverride = null) use (&$urls, $settings): void {
            if (! SeoMetadata::isIndexable($settings, $record, $robots)) {
                return;
            }

            $canonical = SeoMetadata::canonical($settings, $path, $canonicalOverride ?? $record?->seo_canonical_url);
            if (! SeoMetadata::onSiteCanonical($settings, $canonical)) {
                return;
            }

            $urls->push(['loc' => $canonical, 'lastmod' => $updatedAt]);
        };

        $add('/', null, $settings->seo_home_robots, canonicalOverride: $settings->seo_home_canonical);
        $add('/ausgaben', null, $settings->seo_issues_robots, canonicalOverride: $settings->seo_issues_canonical);

        Article::query()->published()->get()->each(fn (Article $article) => $add('/artikel/'.$article->slug, $article, updatedAt: $article->updated_at));
        Issue::query()->get()->each(fn (Issue $issue) => $add('/ausgaben/'.$issue->slug, $issue, updatedAt: $issue->updated_at));
        CmsPage::query()->published()->get()->each(fn (CmsPage $page) => $add('/'.$page->slug, $page, updatedAt: $page->updated_at));

        return response()->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
