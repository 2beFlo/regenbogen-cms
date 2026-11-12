<?php

namespace App\Filament\Pages;

use App\Models\Issue;
use App\Models\IssueEntry;
use App\Support\IssuePlanning\FlatplanCalculator;
use App\Support\IssuePlanning\IssuePlanningAccess;
use BackedEnum;
use Filament\Pages\Page;
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
        $this->selectedIssueId = $issue?->getKey() ?? $issues->first()?->getKey();
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
    }

    public function updatedViewMode(string $viewMode): void
    {
        if (! in_array($viewMode, ['board', 'list'], true)) {
            $this->viewMode = 'list';
        }
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
            'article_status' => $entry->article?->status?->value,
        ];
    }
}
