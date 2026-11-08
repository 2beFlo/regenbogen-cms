<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CmsPage;
use App\Models\Issue;
use App\Models\SiteSetting;
use Illuminate\View\View;

class HomeController
{
    public function __invoke(): View
    {
        $settings = SiteSetting::current();
        $currentIssue = Issue::query()->where('is_current', true)->first();
        $featured = Article::query()
            ->published()
            ->with(['authors', 'issue'])
            ->where('featured', true)
            ->latest('published_at')
            ->first();

        // Der Aufmacher bleibt – wie im freigegebenen WordPress-Layout – auch
        // in der regulären Artikelliste sichtbar. Damit ist „Aktuelle Artikel“
        // ein vollständiger Index und nicht nur eine Resteliste unter dem Hero.
        $articles = Article::query()
            ->published()
            ->with(['authors', 'issue'])
            ->orderByDesc('featured')
            ->latest('published_at')
            ->limit(6)
            ->get();

        $navigation = CmsPage::query()
            ->published()
            ->where('show_in_navigation', true)
            ->orderBy('sort_order')
            ->get();

        $footerPages = CmsPage::query()
            ->published()
            ->where('show_in_footer', true)
            ->orderBy('sort_order')
            ->get();

        return view('home', compact('settings', 'currentIssue', 'featured', 'articles', 'navigation', 'footerPages'));
    }
}
