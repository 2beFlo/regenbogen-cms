<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Issue;
use App\Models\IssueEntry;
use App\Support\IssuePlanning\FlatplanCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class IssuePlanningService
{
    private const CONTENT_TYPES = [
        'article', 'interview', 'comment', 'gloss', 'comic', 'cover', 'closing_page', 'photo', 'infographic', 'events', 'imprint', 'other',
    ];

    private const PLANNING_STATUSES = ['idea', 'planned', 'research', 'writing', 'ready'];

    private const PRINT_STATUSES = ['planned', 'ready_for_layout', 'in_layout', 'layout_ready', 'proofing', 'approved'];

    /** @param array<string, mixed> $data */
    public function saveEntry(Issue $issue, ?IssueEntry $entry, array $data): IssueEntry
    {
        return DB::transaction(function () use ($issue, $entry, $data): IssueEntry {
            $lockedIssue = $this->lockIssue($issue);
            $entries = $this->lockEntries($lockedIssue);

            if ($entry !== null) {
                $entry = $entries->firstWhere('id', $entry->getKey());
                if (! $entry) {
                    throw ValidationException::withMessages(['entry' => 'Der Heftplatz gehört nicht zu dieser Ausgabe.']);
                }
            }

            $values = $this->validatedEntryData($lockedIssue, $entries, $entry, $data);
            $this->assertArticleAvailable($values['article_id'] === null ? null : (int) $values['article_id'], $entry);
            if ($entry?->is_locked && $this->placementChanged($entry, $values)) {
                throw ValidationException::withMessages(['page_start' => 'Dieser Heftplatz ist gesperrt. Entsperre ihn zuerst.']);
            }
            if ($entry !== null && array_key_exists('is_locked', $data) && (bool) $data['is_locked'] !== $entry->is_locked) {
                throw ValidationException::withMessages(['is_locked' => 'Die Sperre wird über die Sperren-Funktion geändert.']);
            }
            $candidateId = $entry?->getKey() ?? -1;
            $this->assertCandidateFits($lockedIssue, $entries, $values, $candidateId);

            $saved = $entry ?? new IssueEntry(['issue_id' => $lockedIssue->getKey()]);
            $saved->fill($values);
            $saved->issue()->associate($lockedIssue);
            $saved->save();

            if (array_key_exists('author_ids', $data)) {
                $saved->authors()->sync($saved->article_id === null ? ($data['author_ids'] ?? []) : []);
            }

            return $saved->fresh(['article.authors', 'authors', 'responsibleUser']);
        });
    }

    public function moveEntry(Issue $issue, int $entryId, int $pageStart, float $pageOffset = 0): IssueEntry
    {
        return DB::transaction(function () use ($issue, $entryId, $pageStart, $pageOffset): IssueEntry {
            $lockedIssue = $this->lockIssue($issue);
            $entries = $this->lockEntries($lockedIssue);
            $entry = $entries->firstWhere('id', $entryId);

            if (! $entry) {
                throw ValidationException::withMessages(['entry' => 'Der Heftplatz gehört nicht zu dieser Ausgabe.']);
            }

            if ($entry->is_locked) {
                throw ValidationException::withMessages(['page_start' => 'Dieser Heftplatz ist gesperrt. Entsperre ihn zuerst.']);
            }

            $this->assertEntryIsMovable($entry);

            return $this->saveEntry($lockedIssue, $entry, [
                ...$entry->only($entry->getFillable()),
                'page_start' => $pageStart,
                'page_offset' => $pageOffset,
                'author_ids' => $entry->authors()->pluck('authors.id')->all(),
            ]);
        });
    }

    public function unplaceEntry(Issue $issue, int $entryId): IssueEntry
    {
        return DB::transaction(function () use ($issue, $entryId): IssueEntry {
            $lockedIssue = $this->lockIssue($issue);
            $entries = $this->lockEntries($lockedIssue);
            $entry = $entries->firstWhere('id', $entryId);

            if (! $entry) {
                throw ValidationException::withMessages(['entry' => 'Der Heftplatz gehört nicht zu dieser Ausgabe.']);
            }

            if ($entry->is_locked) {
                throw ValidationException::withMessages(['page_start' => 'Dieser Heftplatz ist gesperrt. Entsperre ihn zuerst.']);
            }

            $this->assertEntryIsMovable($entry);

            return $this->saveEntry($lockedIssue, $entry, [
                ...$entry->only($entry->getFillable()),
                'page_start' => null,
                'page_offset' => 0,
                'author_ids' => $entry->authors()->pluck('authors.id')->all(),
            ]);
        });
    }

    public function moveEntryToIssue(IssueEntry $entry, Issue $destination): IssueEntry
    {
        return DB::transaction(function () use ($entry, $destination): IssueEntry {
            $sourceId = (int) $entry->issue_id;
            $destinationId = (int) $destination->getKey();

            if ($sourceId === $destinationId) {
                throw ValidationException::withMessages(['issue_id' => 'Wähle eine andere Zielausgabe aus.']);
            }

            $issues = Issue::query()->whereIn('id', [$sourceId, $destinationId])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $issues->get($sourceId);
            $target = $issues->get($destinationId);

            if (! $source || ! $target) {
                throw ValidationException::withMessages(['issue_id' => 'Die Quell- oder Zielausgabe ist nicht verfügbar.']);
            }

            $sourceEntries = $this->lockEntries($source);
            $targetEntries = $this->lockEntries($target);
            $lockedEntry = $sourceEntries->firstWhere('id', $entry->getKey());

            if (! $lockedEntry) {
                throw ValidationException::withMessages(['entry' => 'Der Heftplatz gehört nicht mehr zur Quellausgabe.']);
            }

            if ($lockedEntry->is_locked) {
                throw ValidationException::withMessages(['issue_id' => 'Dieser Heftplatz ist gesperrt. Entsperre ihn zuerst.']);
            }

            $this->assertEntryIsMovable($lockedEntry);

            if ($lockedEntry->article_id !== null && $targetEntries->contains('article_id', $lockedEntry->article_id)) {
                throw ValidationException::withMessages(['issue_id' => 'Der verknüpfte Artikel ist in der Zielausgabe bereits eingeplant.']);
            }

            $lockedEntry->issue()->associate($target);
            $lockedEntry->page_start = null;
            $lockedEntry->page_offset = 0;
            $lockedEntry->save();

            return $lockedEntry->fresh(['article.authors', 'authors', 'responsibleUser']);
        });
    }

    public function setLocked(Issue $issue, int $entryId, bool $locked): IssueEntry
    {
        return DB::transaction(function () use ($issue, $entryId, $locked): IssueEntry {
            $lockedIssue = $this->lockIssue($issue);
            $entry = $this->lockEntries($lockedIssue)->firstWhere('id', $entryId);

            if (! $entry) {
                throw ValidationException::withMessages(['entry' => 'Der Heftplatz gehört nicht zu dieser Ausgabe.']);
            }

            $entry->is_locked = $locked;
            $entry->save();

            return $entry->fresh();
        });
    }

    /**
     * Change Issue metadata atomically with the placement check. Shrinking below
     * an occupied page is rejected; unplaced reservations remain untouched.
     *
     * @param array<string, mixed> $data
     */
    public function updateIssue(Issue $issue, array $data): Issue
    {
        return DB::transaction(function () use ($issue, $data): Issue {
            $lockedIssue = $this->lockIssue($issue);
            $entries = $this->lockEntries($lockedIssue);
            $newPageCount = array_key_exists('page_count', $data) ? $data['page_count'] : $lockedIssue->page_count;

            Validator::make(['page_count' => $newPageCount], [
                'page_count' => ['required', 'integer', 'min:1', 'max:65535'],
            ])->validate();

            $newPageCount = (int) $newPageCount;
            $closingPage = $entries->firstWhere('content_type', 'closing_page');
            $pageCountChanged = $newPageCount !== (int) $lockedIssue->page_count;
            $entriesToValidate = $pageCountChanged && $closingPage
                ? $entries->reject(fn (IssueEntry $entry): bool => $entry->getKey() === $closingPage->getKey())->values()
                : $entries;

            $this->assertNoOccupiedPagesBeyond($newPageCount, $entriesToValidate);

            if ($pageCountChanged && $closingPage) {
                if ($newPageCount < 2) {
                    throw ValidationException::withMessages(['page_count' => 'Die Ausgabe benötigt mindestens zwei Seiten für die geplante Schlussseite.']);
                }

                $candidateIssue = clone $lockedIssue;
                $candidateIssue->page_count = $newPageCount;
                $candidate = [
                    'page_start' => $newPageCount,
                    'page_offset' => 0,
                    'page_span' => 1,
                    'placement_type' => 'normal',
                ];
                try {
                    $this->assertCandidateFits($candidateIssue, $entriesToValidate, $candidate, $closingPage->getKey());
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'page_count' => 'Die Schlussseite kann nicht auf die neue letzte Seite verschoben werden, weil dort bereits Inhalt eingeplant ist.',
                    ]);
                }
            }

            $lockedIssue->fill($data);
            $lockedIssue->save();

            if ($pageCountChanged && $closingPage) {
                $closingPage->page_start = $newPageCount;
                $closingPage->page_offset = 0;
                $closingPage->page_span = 1;
                $closingPage->save();
            }

            return $lockedIssue->fresh();
        });
    }

    public function assertPageCountChange(Issue $issue, int $newPageCount): void
    {
        DB::transaction(function () use ($issue, $newPageCount): void {
            $lockedIssue = $this->lockIssue($issue);
            Validator::make(['page_count' => $newPageCount], [
                'page_count' => ['required', 'integer', 'min:1', 'max:65535'],
            ])->validate();
            $this->assertNoOccupiedPagesBeyond($newPageCount, $this->lockEntries($lockedIssue));
        });
    }

    private function lockIssue(Issue $issue): Issue
    {
        return Issue::query()->whereKey($issue->getKey())->lockForUpdate()->firstOrFail();
    }

    /** @return Collection<int, IssueEntry> */
    private function lockEntries(Issue $issue): Collection
    {
        return IssueEntry::query()->where('issue_id', $issue->getKey())->orderBy('id')->lockForUpdate()->get();
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function validatedEntryData(Issue $issue, Collection $entries, ?IssueEntry $entry, array $data): array
    {
        $defaults = [
            'article_id' => null,
            'title' => '',
            'content_type' => 'other',
            'page_start' => null,
            'page_offset' => 0,
            'page_span' => 1,
            'placement_type' => 'normal',
            'is_locked' => false,
            'planning_status' => 'planned',
            'print_status' => 'planned',
            'responsible_user_id' => null,
            'due_at' => null,
            'internal_notes' => null,
            'author_ids' => [],
        ];
        $values = array_merge($defaults, $entry?->only($entry->getFillable()) ?? [], $data);
        foreach (['article_id', 'page_start', 'responsible_user_id', 'due_at', 'internal_notes'] as $nullableField) {
            if (($values[$nullableField] ?? null) === '') {
                $values[$nullableField] = null;
            }
        }

        $validator = Validator::make($values, [
            'article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'in:'.implode(',', self::CONTENT_TYPES)],
            'page_start' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'page_offset' => ['required', 'numeric', 'min:0', 'lt:1'],
            'page_span' => ['required', 'numeric', 'gt:0', 'max:9999.9999'],
            'placement_type' => ['required', 'string', 'in:normal,spread'],
            'is_locked' => ['required', 'boolean'],
            'planning_status' => ['required', 'string', 'in:'.implode(',', self::PLANNING_STATUSES)],
            'print_status' => ['required', 'string', 'in:'.implode(',', self::PRINT_STATUSES)],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string'],
            'author_ids' => ['array'],
            'author_ids.*' => ['integer', 'exists:authors,id'],
        ]);
        $validator->after(function ($validator) use (&$values, $issue, $entries, $entry): void {
            $article = filled($values['article_id']) ? Article::withTrashed()->find($values['article_id']) : null;

            if (filled($values['article_id']) && ! $article) {
                $validator->errors()->add('article_id', 'Der verknüpfte Artikel ist nicht verfügbar.');
            }

            if (! $article && blank($values['title'])) {
                $validator->errors()->add('title', 'Ein Platzhalter benötigt einen Arbeitstitel.');
            }

            if ($article) {
                $values['title'] = $article->title;
                $values['content_type'] = 'article';
            }

            if ($values['page_start'] === null) {
                $values['page_offset'] = 0;
            }

            if ($values['placement_type'] === 'spread'
                && ($values['page_start'] === null || (int) $values['page_start'] < 2
                    || (int) $values['page_start'] % 2 !== 0
                    || (float) $values['page_offset'] !== 0.0
                    || abs((float) $values['page_span'] - 2.0) > 0.000001)) {
                $validator->errors()->add('page_start', 'Eine Doppelseite beginnt auf einer geraden linken Seite und belegt genau zwei Seiten.');
            }

            $type = $values['content_type'];
            if (in_array($type, ['cover', 'closing_page'], true)) {
                $fixedPage = $type === 'cover' ? 1 : (int) $issue->page_count;
                $fixedTitle = $type === 'cover' ? 'Titelseite' : 'Schlussseite';

                if ($article) {
                    $validator->errors()->add('article_id', 'Titelseite und Schlussseite werden als Platzhalter ohne CMS-Artikel geführt.');
                }

                if ($type === 'closing_page' && (int) $issue->page_count < 2) {
                    $validator->errors()->add('page_start', 'Eine separate Schlussseite benötigt mindestens zwei Seiten.');
                }

                if ((int) $values['page_start'] !== $fixedPage
                    || (float) $values['page_offset'] !== 0.0
                    || abs((float) $values['page_span'] - 1.0) > 0.000001
                    || $values['placement_type'] !== 'normal') {
                    $validator->errors()->add('page_start', 'Der strukturelle Platzhalter ist an seine feste Heftseite gebunden.');
                }

                if ($entries->contains(fn (IssueEntry $item): bool => $item->content_type === $type && $item->getKey() !== $entry?->getKey())) {
                    $validator->errors()->add('content_type', 'Dieser Platzhalter ist in der Ausgabe bereits angelegt.');
                }

                $values['title'] = $fixedTitle;
            } elseif ((int) $values['page_start'] === 1) {
                $validator->errors()->add('page_start', 'Seite 1 ist für den Titelseiten-Platzhalter reserviert und enthält keine Artikel.');
            }
        });

        $validator->validate();

        return collect($values)->except('author_ids')->all();
    }

    private function assertArticleAvailable(?int $articleId, ?IssueEntry $entry): void
    {
        if ($articleId === null) {
            return;
        }

        $article = Article::withTrashed()->whereKey($articleId)->lockForUpdate()->first();
        if (! $article) {
            throw ValidationException::withMessages(['article_id' => 'Der verknüpfte Artikel ist nicht verfügbar.']);
        }

        if ((int) $entry?->article_id === $articleId) {
            return;
        }

        if (IssueEntry::query()->where('article_id', $articleId)
            ->exists()) {
            throw ValidationException::withMessages(['article_id' => 'Dieser CMS-Artikel ist bereits in einer anderen Ausgabe eingeplant.']);
        }
    }

    private function assertEntryIsMovable(IssueEntry $entry): void
    {
        if (in_array($entry->content_type, ['cover', 'closing_page'], true)) {
            throw ValidationException::withMessages(['page_start' => 'Titelseite und Schlussseite bleiben auf ihren festen Heftseiten.']);
        }
    }

    /** @param Collection<int, IssueEntry> $entries
     *  @param array<string, mixed> $candidate
     */
    private function assertCandidateFits(Issue $issue, Collection $entries, array $candidate, int $candidateId): void
    {
        if ($candidate['page_start'] === null) {
            return;
        }

        $pageCount = (int) $issue->page_count;
        if ($pageCount < 1) {
            throw ValidationException::withMessages(['page_start' => 'Lege zuerst die Gesamtseitenzahl der Ausgabe fest.']);
        }

        $rows = $entries->reject(fn (IssueEntry $item): bool => $item->getKey() === $candidateId)
            ->map(fn (IssueEntry $item): array => $this->entryCalculationData($item))
            ->push([
                'id' => $candidateId,
                'page_start' => $candidate['page_start'],
                'page_offset' => $candidate['page_offset'],
                'page_span' => $candidate['page_span'],
                'placement_type' => $candidate['placement_type'],
            ]);

        $conflict = collect(FlatplanCalculator::conflicts($pageCount, $rows->all()))
            ->first(fn (array $conflict): bool => in_array($candidateId, $conflict['entry_ids'], true));

        if ($conflict) {
            $message = match ($conflict['reason']) {
                'overlap' => 'Diese Position überschneidet sich mit einem anderen Heftplatz.',
                'outside_issue' => 'Der Heftplatz reicht über die Gesamtseitenzahl der Ausgabe hinaus.',
                default => 'Die Seitenposition ist ungültig.',
            };
            throw ValidationException::withMessages(['page_start' => $message]);
        }
    }

    /** @param Collection<int, IssueEntry> $entries */
    private function assertNoOccupiedPagesBeyond(int $pageCount, Collection $entries): void
    {
        $conflict = collect(FlatplanCalculator::conflicts(
            $pageCount,
            $entries->map(fn (IssueEntry $entry): array => $this->entryCalculationData($entry))->all(),
        ))->firstWhere('reason', 'outside_issue');

        if ($conflict) {
            throw ValidationException::withMessages([
                'page_count' => "Die Seitenzahl kann nicht auf {$pageCount} sinken: Auf Seite {$conflict['page']} ist bereits Inhalt eingeplant.",
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function entryCalculationData(IssueEntry $entry): array
    {
        return [
            'id' => $entry->getKey(),
            'page_start' => $entry->page_start,
            'page_offset' => $entry->page_offset,
            'page_span' => $entry->page_span,
            'placement_type' => $entry->placement_type,
        ];
    }

    /** @param array<string, mixed> $values */
    private function placementChanged(IssueEntry $entry, array $values): bool
    {
        return $entry->page_start !== ($values['page_start'] === null ? null : (int) $values['page_start'])
            || abs((float) $entry->page_offset - (float) $values['page_offset']) > 0.00001
            || abs((float) $entry->page_span - (float) $values['page_span']) > 0.00001
            || $entry->placement_type !== $values['placement_type'];
    }
}
