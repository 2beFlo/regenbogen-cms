@extends('layouts.site')
@section('title', $article->title)
@section('description', $article->teaser)
@section('content')
<article class="article-page">
    @if($preview)
        <div class="shell preview-banner">Vorschau – dieser Artikel ist möglicherweise noch nicht veröffentlicht.</div>
    @endif

    <header class="article-page-header shell">
        <p class="rubric">{{ $article->rubric_name }}</p>
        <h1>{{ $article->title }}</h1>
        @if($article->teaser)<p class="article-page-dek">{{ $article->teaser }}</p>@endif
        <div class="article-byline">
            <span>Von {{ $article->author_line }}</span>
            @if($article->issue)<span>{{ $article->issue->title }}</span>@elseif($article->published_at)<span>{{ $article->published_at->format('d.m.Y') }}</span>@endif
        </div>
    </header>

    @if($article->hero_image)
        @php
            $extension = strtolower(pathinfo((string) $article->hero_image, PATHINFO_EXTENSION));
            $illustration = in_array($extension, ['png', 'svg'], true);
        @endphp
        <figure class="article-figure{{ $illustration ? ' article-figure-illustration' : '' }}">
            <div class="shell article-figure-inner">
                <x-responsive-image :path="$article->hero_image" sizes="(max-width: 74rem) 100vw, 74rem" loading="eager" fetchpriority="high" />
                @if($article->image_notice)<span class="image-disclosure">{{ $article->image_notice }}</span>@endif
            </div>
        </figure>
    @endif

    <div class="article-reading shell">
        <div class="article-body entry-content">{!! \App\Support\EmailProtector::protectHtml(\App\Support\RichContentSanitizer::sanitize((string) $article->content)) !!}</div>
        <footer class="article-end"><p>Von {{ $article->author_line }}</p></footer>
    </div>
</article>
@endsection
