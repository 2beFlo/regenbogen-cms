@php
    use App\Enums\ArticleStatus;
    use App\Filament\Resources\Articles\ArticleResource;
    use App\Models\Article;

    $user = auth()->user();
    $isEditorial = (bool) $user?->isEditorial();

    $baseOwn = Article::query()->where('created_by', $user?->id);
    $ownDraftCount = (clone $baseOwn)->where('status', ArticleStatus::Draft->value)->count();
    $ownReviewCount = (clone $baseOwn)->where('status', ArticleStatus::Review->value)->count();
    $reviewCount = $isEditorial ? Article::query()->where('status', ArticleStatus::Review->value)->count() : $ownReviewCount;
    $scheduledCount = $isEditorial ? Article::query()
        ->where('status', ArticleStatus::Published->value)
        ->where('published_at', '>', now())
        ->count() : 0;

    $drafts = (clone $baseOwn)
        ->where('status', ArticleStatus::Draft->value)
        ->latest('updated_at')
        ->limit(5)
        ->get();

    $reviews = $isEditorial
        ? Article::query()->with(['creator', 'rubric'])->where('status', ArticleStatus::Review->value)->oldest('review_requested_at')->limit(6)->get()
        : collect();

    $scheduled = $isEditorial
        ? Article::query()->where('status', ArticleStatus::Published->value)->where('published_at', '>', now())->orderBy('published_at')->limit(5)->get()
        : collect();
@endphp

<x-filament-widgets::widget>
    <div class="regenbogen-dashboard">
        <section class="regenbogen-dashboard-welcome">
            <div>
                <p class="regenbogen-dashboard-eyebrow">REGENBOGEN REDAKTION</p>
                <h2>Guten {{ now()->hour < 12 ? 'Morgen' : (now()->hour < 18 ? 'Tag' : 'Abend') }}, {{ $user?->name }}.</h2>
                <p>{{ $isEditorial ? 'Hier siehst du, was in der Redaktion gerade ansteht.' : 'Hier findest du deine Entwürfe und den Stand deiner Freigaben.' }}</p>
            </div>
            <div class="regenbogen-dashboard-actions">
                <a href="{{ ArticleResource::getUrl('create') }}" class="regenbogen-dashboard-primary">✚ Neuen Artikel schreiben</a>
                <a href="{{ url('/') }}" target="_blank">↗ Website ansehen</a>
            </div>
        </section>

        <section class="regenbogen-dashboard-stats">
            <a href="{{ ArticleResource::getUrl() }}">
                <strong>{{ $ownDraftCount }}</strong>
                <span>Meine Entwürfe</span>
            </a>
            <a href="{{ ArticleResource::getUrl() }}">
                <strong>{{ $reviewCount }}</strong>
                <span>{{ $isEditorial ? 'Zur Freigabe' : 'Eingereicht' }}</span>
            </a>
            @if($isEditorial)
                <a href="{{ ArticleResource::getUrl() }}">
                    <strong>{{ $scheduledCount }}</strong>
                    <span>Geplant</span>
                </a>
            @endif
        </section>

        <div class="regenbogen-dashboard-columns">
            @if($isEditorial)
                <section class="regenbogen-dashboard-panel">
                    <div class="regenbogen-dashboard-panel-head">
                        <div>
                            <p class="regenbogen-dashboard-eyebrow">FREIGABE</p>
                            <h3>Wartet auf Redaktion</h3>
                        </div>
                        <span class="regenbogen-dashboard-count">{{ $reviewCount }}</span>
                    </div>
                    @forelse($reviews as $article)
                        <a class="regenbogen-dashboard-row" href="{{ ArticleResource::getUrl('edit', ['record' => $article]) }}">
                            <span>
                                <strong>{{ $article->title }}</strong>
                                <small>{{ $article->rubric_name }} · {{ $article->creator?->name ?? 'Autor*in' }}</small>
                            </span>
                            <span>Prüfen →</span>
                        </a>
                    @empty
                        <p class="regenbogen-dashboard-empty">Gerade wartet kein Artikel auf Freigabe.</p>
                    @endforelse
                </section>
            @endif

            <section class="regenbogen-dashboard-panel">
                <div class="regenbogen-dashboard-panel-head">
                    <div>
                        <p class="regenbogen-dashboard-eyebrow">SCHREIBTISCH</p>
                        <h3>Meine Entwürfe</h3>
                    </div>
                </div>
                @forelse($drafts as $article)
                    <a class="regenbogen-dashboard-row" href="{{ ArticleResource::getUrl('edit', ['record' => $article]) }}">
                        <span>
                            <strong>{{ $article->title }}</strong>
                            <small>zuletzt geändert {{ $article->updated_at->diffForHumans() }}</small>
                        </span>
                        <span>Weiter →</span>
                    </a>
                @empty
                    <p class="regenbogen-dashboard-empty">Keine offenen Entwürfe.</p>
                @endforelse
            </section>
        </div>

        @if($isEditorial && $scheduled->isNotEmpty())
            <section class="regenbogen-dashboard-panel">
                <div class="regenbogen-dashboard-panel-head">
                    <div>
                        <p class="regenbogen-dashboard-eyebrow">VERÖFFENTLICHUNG</p>
                        <h3>Geplant</h3>
                    </div>
                </div>
                @foreach($scheduled as $article)
                    <a class="regenbogen-dashboard-row" href="{{ ArticleResource::getUrl('edit', ['record' => $article]) }}">
                        <span><strong>{{ $article->title }}</strong></span>
                        <span>{{ $article->published_at->format('d.m.Y H:i') }}</span>
                    </a>
                @endforeach
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
