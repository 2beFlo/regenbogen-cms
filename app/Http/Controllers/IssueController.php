<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\Issue;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IssueController
{
    public function index(): View
    {
        $issues = Issue::query()->orderByDesc('published_at')->get();
        return $this->view('issues.index', compact('issues'));
    }

    public function show(string $slug): View
    {
        $issue = Issue::query()->where('slug', $slug)->with(['articles' => fn ($q) => $q->published()->with('authors')])->firstOrFail();
        return $this->view('issues.show', compact('issue'));
    }

    public function download(string $slug)
    {
        $issue = Issue::query()->where('slug', $slug)->firstOrFail();
        abort_unless($issue->pdf_file && Storage::disk('uploads')->exists($issue->pdf_file), 404);

        return response()->download(
            Storage::disk('uploads')->path($issue->pdf_file),
            $issue->pdfDownloadFilename(),
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function view(string $name, array $data): View
    {
        return view($name, $data + [
            'settings' => SiteSetting::current(),
            'navigation' => CmsPage::query()->published()->where('show_in_navigation', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
        ]);
    }
}
