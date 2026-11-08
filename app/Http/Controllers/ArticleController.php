<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CmsPage;
use App\Models\SiteSetting;
use Illuminate\View\View;

class ArticleController
{
    public function show(string $slug): View
    {
        $article = Article::query()->published()->with(['authors', 'issue'])->where('slug', $slug)->firstOrFail();
        return $this->render($article);
    }

    public function preview(Article $article): View
    {
        $user = auth()->user();
        abort_unless($user && ($user->isEditorial() || $article->created_by === $user->id), 403);
        $article->load(['authors', 'issue']);
        return $this->render($article, true);
    }

    private function render(Article $article, bool $preview = false): View
    {
        $settings = SiteSetting::current();
        $navigation = CmsPage::query()->published()->where('show_in_navigation', true)->orderBy('sort_order')->get();
        $footerPages = CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get();
        return view('articles.show', compact('article', 'settings', 'navigation', 'footerPages', 'preview'));
    }
}
