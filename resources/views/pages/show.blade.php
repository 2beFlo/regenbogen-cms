@extends('layouts.site')
@section('title', $page->title)
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
