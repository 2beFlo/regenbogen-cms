<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\SiteSetting;
use Illuminate\View\View;

class PageController
{
    public function show(string $slug): View
    {
        $page = CmsPage::query()->published()->where('slug', $slug)->firstOrFail();
        return view('pages.show', [
            'page' => $page,
            'settings' => SiteSetting::current(),
            'navigation' => CmsPage::query()->published()->where('show_in_navigation', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
        ]);
    }
}
