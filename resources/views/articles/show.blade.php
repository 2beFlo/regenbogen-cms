@extends('layouts.site')
@php
    $seoMetadata = \App\Support\SeoMetadata::resolve(
        $settings,
        '/artikel/'.$article->slug,
        $article,
        defaultTitle: $article->title,
        defaultDescription: \Illuminate\Support\Str::limit((string) $article->teaser, 155, '…'),
        defaultImage: $article->hero_image,
        robotsOverride: $preview ? 'noindex,nofollow' : null,
    );
@endphp
@section('og_type', 'article')
@section('head')
    @php
        $articleSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article->seo_title ?: $article->title,
            'description' => $seoMetadata['description'],
            'mainEntityOfPage' => $seoMetadata['canonical'],
            'author' => $article->authors->map(fn ($author) => ['@type' => 'Person', 'name' => $author->name])->all() ?: [['@type' => 'Organization', 'name' => 'REGENBOGEN Redaktion']],
            'publisher' => ['@id' => \App\Support\SeoMetadata::siteUrl($settings).'#organization'],
            'articleSection' => $article->rubric_name,
        ];
        if ($article->published_at) $articleSchema['datePublished'] = $article->published_at->toAtomString();
        if ($article->updated_at) $articleSchema['dateModified'] = $article->updated_at->toAtomString();
        $articleSchema['image'] = [$seoMetadata['og_image']];
    @endphp
    @if($article->published_at)<meta property="article:published_time" content="{{ $article->published_at->toAtomString() }}">@endif
    @if($article->updated_at)<meta property="article:modified_time" content="{{ $article->updated_at->toAtomString() }}">@endif
    <script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection
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
            @if($article->published_at)<time datetime="{{ $article->published_at->toAtomString() }}">{{ $article->published_at->format('d.m.Y') }}</time>@endif
            @if($article->issue)<span>{{ $article->issue->title }}</span>@endif
        </div>
    </header>

    @if($article->hero_image)
        @php
            $extension = strtolower(pathinfo((string) $article->hero_image, PATHINFO_EXTENSION));
            $illustration = in_array($extension, ['png', 'svg'], true);
        @endphp
        <figure class="article-figure{{ $illustration ? ' article-figure-illustration' : '' }}">
            <div class="shell article-figure-inner">
                <x-responsive-image :path="$article->hero_image" :alt="$article->image_alt ?: $article->title" sizes="(max-width: 74rem) 100vw, 74rem" loading="eager" fetchpriority="high" />
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
