@extends('layouts.site')
@section('title', 'Aktuelle Ausgabe')
@section('content')
@if($featured)
<section class="lead-story shell" aria-labelledby="lead-title">
    <div class="lead-visual">
        @if($featured->hero_image)
            <x-responsive-image :path="$featured->hero_image" class="lead-visual-image" sizes="(max-width: 74rem) 100vw, 74rem" loading="eager" fetchpriority="high" />
        @endif
        <div class="lead-shade" aria-hidden="true"></div>

        @if($featured->image_notice)
            <span class="image-disclosure">{{ $featured->image_notice }}</span>
        @endif

        <div class="lead-heading">
            <p class="rubric rubric-light">{{ $featured->rubric_name }}</p>
            <h1 id="lead-title">{{ $featured->display_title }}</h1>
        </div>

        <a class="card-click-target" href="{{ route('articles.show', $featured->slug) }}" aria-label="{{ $featured->title }} online lesen"></a>
    </div>

    <div class="lead-summary">
        <p class="standfirst">{{ $featured->teaser }}</p>
        <div class="story-meta">
            <span>Von {{ $featured->author_line }}</span>
            @if($featured->issue)<span>{{ $featured->issue->title }}</span>@endif
        </div>
        <a class="text-link" href="{{ route('articles.show', $featured->slug) }}">Artikel online lesen <span aria-hidden="true">→</span></a>
    </div>
</section>
@else
<section class="shell empty-state" aria-labelledby="empty-title">
    <p class="rubric">REGENBOGEN</p>
    <h1 id="empty-title">Die nächste Ausgabe entsteht gerade.</h1>
    <p>Im Redaktionssystem kannst du einen veröffentlichten Artikel als großen Aufmacher markieren.</p>
</section>
@endif

<section class="latest-section shell" id="beitraege" aria-labelledby="beitraege-title">
    <header class="section-heading">
        <span aria-hidden="true"></span>
        <h2 id="beitraege-title">Aktuelle Artikel</h2>
        <img class="brand-mark" src="{{ asset('assets/regenbogen-mark.svg') }}" alt="" aria-hidden="true">
        <span aria-hidden="true"></span>
    </header>

    <div class="article-index">
        @forelse($articles as $article)
            @php
                $cardImage = $article->card_image ?: $article->hero_image;
                $extension = strtolower(pathinfo((string) $cardImage, PATHINFO_EXTENSION));
                $illustration = in_array($extension, ['png', 'svg'], true);
            @endphp
            <article class="article-index-item{{ $illustration ? ' article-index-item-illustration' : '' }}">
                <a class="article-index-image" href="{{ route('articles.show', $article->slug) }}" aria-label="{{ $article->title }} online lesen">
                    @if($cardImage)
                        <x-responsive-image :path="$cardImage" sizes="(max-width: 46rem) 100vw, 35rem" />
                    @else
                        <span class="article-image-placeholder" aria-hidden="true"></span>
                    @endif
                </a>

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
            <div class="empty-state">
                <h2>Noch keine Artikel</h2>
                <p>Veröffentlichte Beiträge erscheinen hier automatisch.</p>
            </div>
        @endforelse
    </div>
</section>

@if($currentIssue)
<section class="issue-section shell" id="ausgaben" aria-labelledby="ausgaben-title">
    <div class="issue-card">
        <a class="issue-cover" href="{{ route('issues.show', $currentIssue->slug) }}">
            @if($currentIssue->cover_image)
                <x-responsive-image :path="$currentIssue->cover_image" alt="Titelseite der aktuellen REGENBOGEN-Ausgabe" sizes="(max-width: 46rem) 70vw, 22rem" />
            @endif
        </a>

        <div class="issue-copy">
            <p class="rubric rubric-light">Heftarchiv</p>
            <h2 id="ausgaben-title">{{ $currentIssue->title }}</h2>
            <p class="issue-intro">Alle Beiträge lassen sich direkt online lesen. Zusätzlich gibt es die vollständige Ausgabe im originalen A5-Layout.</p>

            <dl class="issue-facts">
                <div><dt>Ausgabe</dt><dd>{{ $currentIssue->subtitle ?: $currentIssue->title }}</dd></div>
                <div><dt>Umfang</dt><dd>{{ $currentIssue->page_count ?: '–' }} Seiten, {{ $currentIssue->format }}</dd></div>
                <div><dt>Format</dt><dd>PDF</dd></div>
            </dl>

            <div class="button-row">
                <a class="button button-light" href="{{ route('issues.show', $currentIssue->slug) }}">Ausgabe lesen</a>
                @if($currentIssue->pdf_file)
                    <a class="button button-outline" href="{{ route('issues.download', $currentIssue->slug) }}">PDF herunterladen</a>
                @endif
            </div>
        </div>
    </div>
</section>
@endif

<section class="about shell" id="ueber-uns" aria-labelledby="about-title">
    <div>
        <p class="rubric">Über uns</p>
        <h2 id="about-title">{{ $settings->about_title }}</h2>
    </div>
    <div class="about-copy">
        <p>{{ $settings->about_text }}</p>
        @if($settings->about_link_url)
            <p><a class="text-link" href="{{ $settings->about_link_url }}">{{ $settings->about_link_label }} <span aria-hidden="true">→</span></a></p>
        @endif
    </div>
</section>
@endsection
