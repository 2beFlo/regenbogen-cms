@extends('layouts.site')
@section('title', 'Ausgaben')
@section('content')
<section class="archive-page shell">
    <header class="archive-header">
        <p class="rubric">Heftarchiv</p>
        <h1>Ausgaben</h1>
    </header>

    <div class="archive-grid">
        @forelse($issues as $issue)
            <article class="archive-card issue-archive-card">
                <a class="archive-card-image issue-archive-cover" href="{{ route('issues.show', $issue->slug) }}">
                    @if($issue->cover_image)
                        <x-responsive-image :path="$issue->cover_image" alt="Cover {{ $issue->title }}" sizes="(max-width: 46rem) 70vw, 18rem" />
                    @else
                        <span class="article-image-placeholder" aria-hidden="true"></span>
                    @endif
                </a>
                <p class="rubric">{{ optional($issue->published_at)->format('d.m.Y') ?: 'REGENBOGEN' }}</p>
                <h2><a href="{{ route('issues.show', $issue->slug) }}">{{ $issue->title }}</a></h2>
                @if($issue->subtitle)<p>{{ $issue->subtitle }}</p>@endif
                <a class="text-link" href="{{ route('issues.show', $issue->slug) }}">Ausgabe ansehen <span aria-hidden="true">→</span></a>
            </article>
        @empty
            <div class="empty-state"><h2>Noch keine Ausgaben</h2><p>Veröffentlichte Ausgaben erscheinen hier automatisch.</p></div>
        @endforelse
    </div>
</section>
@endsection
