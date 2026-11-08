<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CmsPage;
use App\Models\Issue;
use Illuminate\Http\Response;

class SeoController
{
    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /impressum',
            'Disallow: /datenschutz',
            'Disallow: /kontakt',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $excludedPages = ['impressum', 'datenschutz', 'datenschutzerklaerung', 'kontakt'];

        $urls = collect([
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('issues.index'), 'lastmod' => null],
        ])
            ->merge(Article::query()->published()->get()->map(fn (Article $article): array => [
                'loc' => route('articles.show', $article->slug),
                'lastmod' => $article->updated_at,
            ]))
            ->merge(Issue::query()->get()->map(fn (Issue $issue): array => [
                'loc' => route('issues.show', $issue->slug),
                'lastmod' => $issue->updated_at,
            ]))
            ->merge(CmsPage::query()->published()->whereNotIn('slug', $excludedPages)->get()->map(fn (CmsPage $page): array => [
                'loc' => route('pages.show', $page->slug),
                'lastmod' => $page->updated_at,
            ]));

        return response()->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
