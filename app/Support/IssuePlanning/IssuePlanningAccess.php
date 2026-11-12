<?php

namespace App\Support\IssuePlanning;

use App\Enums\UserRole;
use App\Models\Issue;
use App\Models\IssueEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class IssuePlanningAccess
{
    public function canAccess(?User $user): bool
    {
        return $user !== null && in_array($user->role, [
            UserRole::Administration,
            UserRole::Redaktion,
            UserRole::Redakteur,
        ], true);
    }

    public function canMutate(?User $user): bool
    {
        return (bool) $user?->isEditorial();
    }

    public function canSeeInternalNotes(?User $user): bool
    {
        return $this->canMutate($user);
    }

    public function canViewIssue(?User $user, Issue $issue): bool
    {
        if (! $this->canAccess($user)) {
            return false;
        }

        return $this->canMutate($user)
            || $issue->entries()->where(fn (Builder $query) => $this->applyOwnEntryScope($query, $user))->exists();
    }

    /** @return Collection<int, Issue> */
    public function visibleIssues(?User $user): Collection
    {
        if (! $this->canAccess($user)) {
            return Issue::query()->whereRaw('1 = 0')->get();
        }

        $query = Issue::query()
            ->when(! $this->canMutate($user), fn (Builder $query) => $query->whereHas(
                'entries',
                fn (Builder $entries) => $this->applyOwnEntryScope($entries, $user),
            ))
            ->orderByDesc('planned_publication_date')
            ->orderByDesc('published_at')
            ->orderBy('title');

        if (! $this->canMutate($user)) {
            $query->select(['id', 'title', 'issue_code', 'work_title', 'page_count', 'planned_publication_date', 'published_at', 'production_status']);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, IssueEntry>
     *
     * Author queries intentionally omit internal_notes from the selected columns.
     */
    public function visibleEntries(?User $user, Issue $issue): Collection
    {
        if (! $this->canViewIssue($user, $issue)) {
            return new Collection();
        }

        $query = IssueEntry::query()->where('issue_id', $issue->getKey());

        if (! $this->canMutate($user)) {
            $query->where(fn (Builder $entries) => $this->applyOwnEntryScope($entries, $user))
                ->select([
                    'id', 'issue_id', 'article_id', 'title', 'content_type', 'page_start', 'page_offset', 'page_span',
                    'placement_type', 'is_locked', 'planning_status', 'print_status', 'responsible_user_id', 'due_at', 'created_at', 'updated_at',
                ]);
        }

        return $query
            ->with([
                'article' => fn ($article) => $article->select(['id', 'created_by', 'title', 'category', 'status']),
                'article.authors:id,name,slug,user_id',
                'authors:id,name,slug,user_id',
                'responsibleUser:id,name',
            ])
            ->orderByRaw('page_start is null')
            ->orderBy('page_start')
            ->orderBy('page_offset')
            ->get();
    }

    private function applyOwnEntryScope(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $entries) use ($user): void {
            $entries->where('responsible_user_id', $user->getKey())
                ->orWhereHas('article', fn (Builder $article) => $article->where('created_by', $user->getKey()))
                ->orWhereHas('authors', fn (Builder $authors) => $authors->where('authors.user_id', $user->getKey()))
                ->orWhereHas('article.authors', fn (Builder $authors) => $authors->where('authors.user_id', $user->getKey()));
        });
    }
}
