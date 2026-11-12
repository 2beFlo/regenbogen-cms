<?php

namespace App\Filament\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\Author;
use App\Models\Issue;
use App\Models\IssueEntry;
use App\Models\User;
use App\Services\IssuePlanningService;
use App\Support\IssuePlanning\FlatplanCalculator;
use App\Support\IssuePlanning\IssuePlanningAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class IssuePlanning extends Page
{
    protected string $view = 'filament.pages.issue-planning';

    protected static ?string $slug = 'issue-planning';

    protected static ?string $navigationLabel = 'Ausgabenplanung';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 5;

    public ?int $selectedIssueId = null;

    public string $viewMode = 'board';

    public bool $entryFormOpen = false;

    public ?int $editingEntryId = null;

    /** @var array<string, mixed> */
    public array $entryForm = [];

    /** @var array<int, array{page_start: int|string|null, page_offset: int|float|string}> */
    public array $manualPlacement = [];

    /** @var array<int, int|string|null> */
    public array $transferTarget = [];

    public function getTitle(): string
    {
        return 'Ausgabenplanung';
    }

    public static function canAccess(): bool
    {
        return app(IssuePlanningAccess::class)->canAccess(auth()->user());
    }

    public function mount(): void
    {
        $requestedIssue = request()->query('issue');
        $issues = $this->issues();
        $issue = $requestedIssue ? $issues->firstWhere('id', (int) $requestedIssue) : null;
        $this->selectedIssueId = $issue?->getKey() ?? $this->defaultIssue($issues)?->getKey();
        $this->syncManualPlacements();
    }

    /** @return Collection<int, Issue> */
    public function issues(): Collection
    {
        return app(IssuePlanningAccess::class)->visibleIssues(auth()->user());
    }

    public function selectedIssue(): ?Issue
    {
        if (! $this->selectedIssueId) {
            return null;
        }

        return $this->issues()->firstWhere('id', $this->selectedIssueId);
    }

    /** @return Collection<int, IssueEntry> */
    public function entries(): Collection
    {
        $issue = $this->selectedIssue();

        return $issue
            ? app(IssuePlanningAccess::class)->visibleEntries(auth()->user(), $issue)
            : new Collection();
    }

    public function canMutatePlan(): bool
    {
        return app(IssuePlanningAccess::class)->canMutate(auth()->user());
    }

    public function canSeeInternalNotes(): bool
    {
        return app(IssuePlanningAccess::class)->canSeeInternalNotes(auth()->user());
    }

    public function selectIssue(int $issueId): void
    {
        abort_unless($this->issues()->contains(fn (Issue $issue): bool => $issue->getKey() === $issueId), 403);
        $this->selectedIssueId = $issueId;
        $this->resetValidation();
        $this->entryFormOpen = false;
        $this->editingEntryId = null;
        $this->syncManualPlacements();
    }

    public function updatedSelectedIssueId(): void
    {
        if (! $this->issues()->contains(fn (Issue $issue): bool => $issue->getKey() === (int) $this->selectedIssueId)) {
            $this->selectedIssueId = $this->defaultIssue($this->issues())?->getKey();
        }

        $this->resetValidation();
        $this->entryFormOpen = false;
        $this->editingEntryId = null;
        $this->syncManualPlacements();
    }

    public function updatedViewMode(string $viewMode): void
    {
        if (! in_array($viewMode, ['board', 'list'], true)) {
            $this->viewMode = 'list';
        }
    }

    public function openEntryForm(?int $pageStart = null): void
    {
        $this->assertCanMutate();
        $this->assertSelectedIssue();

        if ($pageStart === 1) {
            $this->openCoverPlaceholder();

            return;
        }

        $this->resetValidation();
        $this->editingEntryId = null;
        $this->entryForm = $this->defaultEntryForm($pageStart);
        $this->entryFormOpen = true;
    }

    public function openCoverPlaceholder(): void
    {
        $this->openStructuralPlaceholder('cover', 1, 'Titelseite');
    }

    public function openClosingPagePlaceholder(): void
    {
        $issue = $this->assertSelectedIssue();
        if ((int) $issue->page_count < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'page_start' => 'Eine separate Schlussseite benötigt mindestens zwei Seiten.',
            ]);
        }

        $this->openStructuralPlaceholder('closing_page', (int) $issue->page_count, 'Schlussseite');
    }

    private function openStructuralPlaceholder(string $contentType, int $pageStart, string $title): void
    {
        $this->assertCanMutate();
        $issue = $this->assertSelectedIssue();
        $this->resetValidation();
        $this->editingEntryId = null;

        $existing = $issue->entries()->where('content_type', $contentType)->first();
        if ($existing && $existing->page_start === null && $existing->article_id === null) {
            app(IssuePlanningService::class)->placeStructuralPlaceholder($issue, $existing->getKey());
            $this->syncManualPlacements();
            Notification::make()->success()->title($title.' auf Seite '.$pageStart.' platziert')->send();

            return;
        }

        if ($existing) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'page_start' => 'Dieser Platzhalter ist in der Ausgabe bereits angelegt.',
            ]);
        }

        $this->entryForm = [
            ...$this->defaultEntryForm($pageStart),
            'title' => $title,
            'content_type' => $contentType,
            'page_span' => 1,
        ];
        $this->entryFormOpen = true;
    }

    public function isStructuralEntryForm(): bool
    {
        return in_array($this->entryForm['content_type'] ?? null, ['cover', 'closing_page'], true);
    }

    public function selectedArticle(): ?Article
    {
        $articleId = $this->entryForm['article_id'] ?? null;

        return filled($articleId) && ! $this->isStructuralEntryForm()
            ? Article::withTrashed()->with(['authors', 'rubric'])->find((int) $articleId)
            : null;
    }

    public function updatedEntryFormArticleId(mixed $articleId): void
    {
        if (blank($articleId) || $this->isStructuralEntryForm()) {
            return;
        }

        $article = Article::query()->with('authors')->find((int) $articleId);
        if (! $article) {
            $this->entryForm['article_id'] = '';

            return;
        }

        $this->entryForm['title'] = $article->title;
        $this->entryForm['content_type'] = 'article';
        $this->entryForm['author_ids'] = $article->authors->pluck('id')->all();
    }

    public function openEditEntry(int $entryId): void
    {
        $this->assertCanMutate();
        $this->resetValidation();
        $entry = $this->editableEntry($entryId);
        $this->editingEntryId = $entry->getKey();
        $this->entryForm = [
            'article_id' => $entry->article_id,
            'title' => $entry->article?->title ?: $entry->title,
            'content_type' => $entry->content_type,
            'page_start' => $entry->page_start,
            'page_offset' => $entry->page_start === null ? 0 : $this->quarterStep($entry->page_offset),
            'page_span' => max(0.25, $this->quarterStep($entry->page_span)),
            'placement_type' => $entry->placement_type,
            'is_locked' => $entry->is_locked,
            'planning_status' => $entry->planning_status,
            'print_status' => $entry->print_status,
            'responsible_user_id' => $entry->responsible_user_id,
            'due_at' => $entry->due_at?->format('Y-m-d'),
            'internal_notes' => $entry->internal_notes,
            'author_ids' => $entry->article
                ? $entry->article->authors()->pluck('authors.id')->all()
                : $entry->authors()->pluck('authors.id')->all(),
        ];
        $this->entryFormOpen = true;
    }

    public function closeEntryForm(): void
    {
        $this->resetValidation();
        $this->entryFormOpen = false;
        $this->editingEntryId = null;
    }

    public function saveEntry(): void
    {
        $this->assertCanMutate();
        $issue = $this->assertSelectedIssue();
        $entry = $this->editingEntryId ? $this->editableEntry($this->editingEntryId) : null;

        app(IssuePlanningService::class)->saveEntry($issue, $entry, $this->entryForm);
        $this->entryFormOpen = false;
        $this->editingEntryId = null;
        Notification::make()->success()->title('Heftplatz gespeichert')->send();
    }

    public function removeUnplacedStructuralPlaceholder(int $entryId): void
    {
        $this->assertCanMutate();
        app(IssuePlanningService::class)->deleteUnplacedStructuralPlaceholder($this->assertSelectedIssue(), $entryId);
        Notification::make()->success()->title('Platzhalter gelöscht')->send();
    }

    public function moveEntryToPage(int $entryId, int $pageStart, float $pageOffset = 0): void
    {
        $this->assertCanMutate();
        app(IssuePlanningService::class)->moveEntry($this->assertSelectedIssue(), $entryId, $pageStart, $pageOffset);
        Notification::make()->success()->title('Beitrag verschoben')->send();
    }

    public function manualMoveEntry(int $entryId): void
    {
        $placement = $this->manualPlacement[$entryId] ?? [];
        $pageStart = $placement['page_start'] ?? null;
        if (! is_numeric($pageStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['page_start' => 'Wähle zuerst eine Zielseite aus.']);
        }

        $this->moveEntryToPage($entryId, (int) $pageStart, (float) ($placement['page_offset'] ?? 0));
    }

    public function transferEntryToIssue(int $entryId): void
    {
        $this->assertCanMutate();
        $source = $this->assertSelectedIssue();
        $entry = $this->editableEntry($entryId);
        $targetId = $this->transferTarget[$entryId] ?? null;
        $target = $this->issues()->firstWhere('id', (int) $targetId);
        abort_unless($target && $target->getKey() !== $source->getKey(), 404);

        app(IssuePlanningService::class)->moveEntryToIssue($entry, $target);
        unset($this->transferTarget[$entryId], $this->manualPlacement[$entryId]);
        Notification::make()->success()->title('Beitrag verschoben')->body('Er wurde in der Zielausgabe unter „Noch nicht platziert“ übernommen.')->send();
    }

    /** @return Collection<int, Issue> */
    public function transferTargets(): Collection
    {
        $issueId = $this->selectedIssue()?->getKey();

        return $this->canMutatePlan()
            ? $this->issues()->reject(fn (Issue $issue): bool => $issue->getKey() === $issueId)->values()
            : new Collection();
    }

    public function unplaceEntry(int $entryId): void
    {
        $this->assertCanMutate();
        app(IssuePlanningService::class)->unplaceEntry($this->assertSelectedIssue(), $entryId);
        Notification::make()->success()->title('Beitrag in „Noch nicht platziert“ verschoben')->send();
    }

    public function toggleEntryLock(int $entryId): void
    {
        $this->assertCanMutate();
        $entry = $this->editableEntry($entryId);
        $locked = ! $entry->is_locked;
        app(IssuePlanningService::class)->setLocked($this->assertSelectedIssue(), $entryId, $locked);
        Notification::make()->success()->title($locked ? 'Heftplatz gesperrt' : 'Heftplatz entsperrt')->send();
    }

    /** @return array<int, string> */
    public function articleOptions(): array
    {
        if (! $this->canMutatePlan() || $this->isStructuralEntryForm()) {
            return [];
        }

        $issueId = $this->selectedIssue()?->getKey();
        $query = Article::query()->where(function (Builder $query) use ($issueId): void {
            $query->whereDoesntHave('issueEntries');

            if ($this->editingEntryId && $issueId) {
                $query->orWhereHas('issueEntries', fn (Builder $entries): Builder => $entries
                    ->whereKey($this->editingEntryId)
                    ->where('issue_id', $issueId));
            }
        });

        $options = $query->orderBy('title')->pluck('title', 'id')->all();

        if ($this->editingEntryId) {
            $currentArticle = $this->editableEntry($this->editingEntryId)->article;
            if ($currentArticle && ! array_key_exists($currentArticle->getKey(), $options)) {
                $options[$currentArticle->getKey()] = $currentArticle->title;
            }
        }

        return $options;
    }

    /** @return array<int, string> */
    public function authorOptions(): array
    {
        return $this->canMutatePlan() ? Author::query()->orderBy('name')->pluck('name', 'id')->all() : [];
    }

    /** @return array<int, string> */
    public function responsibleUserOptions(): array
    {
        return $this->canMutatePlan()
            ? User::query()->orderBy('name')->pluck('name', 'id')->all()
            : [];
    }

    public function canEditLinkedArticle(IssueEntry $entry): bool
    {
        $user = auth()->user();

        return $entry->article !== null && ($user?->isEditorial()
            || $entry->article->created_by === $user?->getKey() && $entry->article->status === ArticleStatus::Draft);
    }

    public function articleEditUrl(IssueEntry $entry): ?string
    {
        return $this->canEditLinkedArticle($entry)
            ? ArticleResource::getUrl('edit', ['record' => $entry->article])
            : null;
    }

    /** @return array<string, string> */
    public function contentTypeOptions(): array
    {
        return [
            'article' => 'Artikel', 'interview' => 'Interview', 'comment' => 'Kommentar', 'gloss' => 'Glosse',
            'comic' => 'WerDi-Comic', 'cover' => 'Titelseite', 'closing_page' => 'Schlussseite',
            'photo' => 'Fotoseite', 'infographic' => 'Infografik', 'events' => 'Termine',
            'imprint' => 'Impressum', 'other' => 'Sonstiger Inhalt',
        ];
    }

    /** @return array<string, string> */
    public function entryContentTypeOptions(): array
    {
        return collect($this->contentTypeOptions())->except(['cover', 'closing_page'])->all();
    }

    public function editorialStatusLabel(IssueEntry $entry): string
    {
        return match ($entry->planning_status) {
            'idea' => 'Idee', 'planned' => 'In Planung', 'research' => 'Recherche',
            'writing' => 'In Arbeit', 'ready' => 'Redaktionell fertig', default => 'Unbekannt',
        };
    }

    public function printStatusLabel(IssueEntry $entry): string
    {
        return match ($entry->print_status) {
            'planned' => 'Vorgesehen', 'ready_for_layout' => 'Bereit für den Satz', 'in_layout' => 'Im Satz',
            'layout_ready' => 'Gesetzte Fassung vorhanden', 'proofing' => 'Schlusskorrektur',
            'approved' => 'Druckfreigabe', default => 'Unbekannt',
        };
    }

    public function issueProductionStatusLabel(?Issue $issue): string
    {
        return match ($issue?->production_status) {
            'editorial' => 'Redaktion', 'layout' => 'Im Satz', 'proofing' => 'Schlusskorrektur',
            'approved' => 'Druckfreigabe', 'printed' => 'Gedruckt', 'archived' => 'Abgeschlossen',
            default => 'In Planung',
        };
    }

    /** @return array<int, array{left: ?int, right: ?int}> */
    public function spreads(): array
    {
        return FlatplanCalculator::spreads(max(0, (int) ($this->selectedIssue()?->page_count ?? 0)));
    }

    /** @return array<int, array<int, array{entry_id: int, offset: float, extent: float}>> */
    public function segments(): array
    {
        return FlatplanCalculator::segments(
            max(0, (int) ($this->selectedIssue()?->page_count ?? 0)),
            $this->entries()->map(fn (IssueEntry $entry): array => $this->entryData($entry))->all(),
        );
    }

    /** @return array<string, int|float> */
    public function summary(): array
    {
        $entries = $this->entries()->map(fn (IssueEntry $entry): array => $this->entryData($entry))->all();

        return FlatplanCalculator::summarize(max(0, (int) ($this->selectedIssue()?->page_count ?? 0)), $entries);
    }

    /** @return array<string, mixed> */
    private function entryData(IssueEntry $entry): array
    {
        return [
            'id' => $entry->getKey(),
            'page_start' => $entry->page_start,
            'page_offset' => $entry->page_offset,
            'page_span' => $entry->page_span,
            'placement_type' => $entry->placement_type,
            'planning_status' => $entry->planning_status,
            'print_status' => $entry->print_status,
        ];
    }

    /** @return array<string, mixed> */
    private function defaultEntryForm(?int $pageStart): array
    {
        return [
            'article_id' => '', 'title' => '', 'content_type' => 'other', 'page_start' => $pageStart,
            'page_offset' => 0, 'page_span' => 1, 'placement_type' => 'normal', 'is_locked' => false,
            'planning_status' => 'idea', 'print_status' => 'planned', 'responsible_user_id' => '',
            'due_at' => '', 'internal_notes' => '', 'author_ids' => [],
        ];
    }

    private function assertCanMutate(): void
    {
        abort_unless($this->canMutatePlan(), 403);
    }

    private function syncManualPlacements(): void
    {
        $this->manualPlacement = [];

        foreach ($this->entries() as $entry) {
            $this->manualPlacement[$entry->getKey()] = [
                'page_start' => $entry->page_start ?? '',
                'page_offset' => $this->quarterStep($entry->page_offset),
            ];
        }
    }

    private function assertSelectedIssue(): Issue
    {
        $issue = $this->selectedIssue();
        abort_unless($issue && app(IssuePlanningAccess::class)->canViewIssue(auth()->user(), $issue), 404);

        return $issue;
    }

    private function editableEntry(int $entryId): IssueEntry
    {
        return $this->entries()->firstWhere('id', $entryId)
            ?? abort(404);
    }

    private function defaultIssue(Collection $issues): ?Issue
    {
        $today = now()->startOfDay();

        return $issues
            ->filter(fn (Issue $issue): bool => $issue->planned_publication_date !== null
                && $issue->planned_publication_date->greaterThanOrEqualTo($today)
                && $issue->published_at === null
                && $issue->production_status !== 'archived')
            ->sortBy(fn (Issue $issue): int => $issue->planned_publication_date->getTimestamp())
            ->first()
            ?? $issues
                ->filter(fn (Issue $issue): bool => $issue->published_at === null
                    && $issue->production_status === 'planning')
                ->sortByDesc(fn (Issue $issue): int => (int) $issue->getKey())
                ->first()
            ?? $issues->firstWhere('is_current', true)
            ?? $issues->first();
    }

    private function quarterStep(float|int|string|null $value): float
    {
        return round((float) $value * 4) / 4;
    }
}
