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
            'Sitemap: https://regenbogen-duderstadt.de/sitemap.xml',
            '',
        ]);

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $excludedPages = ['impressum', 'datenschutz', 'datenschutzerklaerung'];
        $canonical = static fn (string $path): string => 'https://regenbogen-duderstadt.de'.($path === '/' ? '/' : '/'.ltrim($path, '/'));

        $urls = collect([
            ['loc' => $canonical(route('home', [], false)), 'lastmod' => null],
            ['loc' => $canonical(route('issues.index', [], false)), 'lastmod' => null],
        ])
            ->merge(Article::query()->published()->get()->map(fn (Article $article): array => [
                'loc' => $canonical(route('articles.show', $article->slug, false)),
                'lastmod' => $article->updated_at,
            ]))
            ->merge(Issue::query()->get()->map(fn (Issue $issue): array => [
                'loc' => $canonical(route('issues.show', $issue->slug, false)),
                'lastmod' => $issue->updated_at,
            ]))
            ->merge(CmsPage::query()->published()->whereNotIn('slug', $excludedPages)->get()->map(fn (CmsPage $page): array => [
                'loc' => $canonical(route('pages.show', $page->slug, false)),
                'lastmod' => $page->updated_at,
            ]));

        return response()->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
