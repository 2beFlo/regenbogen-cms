@extends('layouts.site')
@php
    $pageContentForDescription = \App\Support\RichContentSanitizer::sanitize((string) $page->content);
    $pageContentForDescription = preg_replace('/<(?:\/(?:p|div|li|h[1-6]|blockquote|section|article|tr|td)|br\s*\/?)[^>]*>/i', ' ', $pageContentForDescription) ?: '';
    $plainPageContent = html_entity_decode(strip_tags($pageContentForDescription), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plainPageContent = trim(preg_replace('/\s+/u', ' ', $plainPageContent) ?: '');
    $pageDescription = $page->seo_description ?: \Illuminate\Support\Str::limit($plainPageContent, 155, '…');
@endphp
@section('title', $page->seo_title ?: $page->title.' aus Duderstadt')
@section('description', $pageDescription)
@if(in_array($page->slug, ['impressum', 'datenschutz', 'datenschutzerklaerung'], true))
    @section('robots', 'noindex,nofollow')
@endif
@section('content')
<article class="page-shell shell">
    <header class="page-header">
        <p class="rubric">REGENBOGEN</p>
        <h1>{{ $page->title }}</h1>
    </header>
    <div class="entry-content">{!! \App\Support\EmailProtector::protectHtml(\App\Support\RichContentSanitizer::sanitize((string) $page->content)) !!}</div>
    @if($page->slug === 'unterstuetze-uns')
        @include('pages.partials.twingle-support')
    @endif
</article>
@endsection
