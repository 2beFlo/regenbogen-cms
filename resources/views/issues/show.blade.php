@extends('layouts.site')
@section('title', $issue->seo_title ?: $issue->title.' – Ausgabe')
@section('description', $issue->seo_description ?: \Illuminate\Support\Str::limit(trim(implode(' ', array_filter([$issue->subtitle, $issue->published_at?->format('Y'), 'REGENBOGEN aus Duderstadt und dem Untereichsfeld. Beiträge online lesen und die Ausgabe als PDF herunterladen.']))), 155, '…'))
@section('content')
<section class="archive-page shell">
    <header class="archive-header">
        <p class="rubric">Ausgabe</p>
        <h1>{{ $issue->title }}</h1>
    </header>

    <div class="issue-detail">
        <div class="issue-detail-cover">
            @if($issue->cover_image)<x-responsive-image :path="$issue->cover_image" alt="Cover {{ $issue->title }}" sizes="(max-width: 46rem) 70vw, 18rem" />@endif
        </div>
        <div class="issue-detail-copy">
            @if($issue->subtitle)<p class="issue-detail-lead">{{ $issue->subtitle }}</p>@endif
            <p>{{ $issue->page_count ?: '–' }} Seiten · {{ $issue->format }}</p>
            @if($issue->pdf_file)<a class="button button-green" href="{{ route('issues.download', $issue->slug) }}">PDF herunterladen</a>@endif
        </div>
    </div>

    <div class="article-index issue-articles">
        @forelse($issue->articles as $article)
            <article class="article-index-item issue-article-item">
                <div class="article-index-title">
                    <p class="rubric">{{ $article->rubric_name }}</p>
                    <h3><a href="{{ route('articles.show', $article->slug) }}">{{ $article->display_title }}</a></h3>
                </div>
                <div class="article-index-copy">
                    <p>{{ $article->teaser }}</p>
                    <div class="story-meta"><span>Von {{ $article->author_line }}</span></div>
                    <a class="text-link" href="{{ route('articles.show', $article->slug) }}">Online lesen <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @empty
            <div class="empty-state"><h2>Noch keine Artikel</h2><p>Dieser Ausgabe sind noch keine veröffentlichten Artikel zugeordnet.</p></div>
        @endforelse
    </div>
</section>
@endsection
