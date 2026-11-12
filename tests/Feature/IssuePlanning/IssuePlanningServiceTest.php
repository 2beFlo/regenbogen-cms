<?php

namespace Tests\Feature\IssuePlanning;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Issue;
use App\Models\IssueEntry;
use App\Services\IssuePlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IssuePlanningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_dynamic_odd_page_counts_and_keeps_unplaced_reservations_on_shrink(): void
    {
        $issue = $this->issue(32);
        $this->entry($issue, ['title' => 'Interview', 'page_span' => 4, 'page_start' => null]);
        $service = app(IssuePlanningService::class);

        $service->updateIssue($issue, ['page_count' => 23]);

        self::assertSame(23, $issue->fresh()->page_count);
        self::assertSame(4.0, $issue->entries()->sole()->page_span);
        $service->updateIssue($issue->fresh(), ['page_count' => 31]);
        self::assertSame(31, $issue->fresh()->page_count);
    }

    public function test_it_rejects_page_shrink_below_occupied_content_without_changing_the_plan(): void
    {
        $issue = $this->issue(8);
        $entry = $this->entry($issue, ['title' => 'Platzhalter', 'page_start' => 7, 'page_span' => 2]);

        try {
            app(IssuePlanningService::class)->updateIssue($issue, ['page_count' => 6]);
            self::fail('The occupied last page must prevent shrinking the issue.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('page_count', $exception->errors());
        }

        self::assertSame(8, $issue->fresh()->page_count);
        self::assertSame(7, $entry->fresh()->page_start);
        self::assertDatabaseHas('issue_entries', ['id' => $entry->id, 'title' => 'Platzhalter']);
    }

    public function test_page_count_must_be_an_integer_within_the_database_page_range(): void
    {
        $issue = $this->issue(8);

        foreach ([0, 8.5, 65536] as $invalidPageCount) {
            try {
                app(IssuePlanningService::class)->updateIssue($issue, ['page_count' => $invalidPageCount]);
                self::fail('Invalid page counts must not be stored.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('page_count', $exception->errors());
            }
        }

        self::assertSame(8, $issue->fresh()->page_count);
    }

    public function test_it_rejects_a_move_that_overlaps_another_entry_without_overwriting_it(): void
    {
        $issue = $this->issue(8);
        $first = $this->entry($issue, ['title' => 'Beitrag A', 'page_start' => 4, 'page_span' => 0.5]);
        $second = $this->entry($issue, ['title' => 'Beitrag B', 'page_start' => 6, 'page_span' => 1]);

        try {
            app(IssuePlanningService::class)->moveEntry($issue, $second->id, 4, 0.25);
            self::fail('Overlapping entries must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('page_start', $exception->errors());
        }

        self::assertSame(6, $second->fresh()->page_start);
        self::assertSame(4, $first->fresh()->page_start);
    }

    public function test_a_regular_two_page_entry_is_distinct_from_a_true_facing_spread(): void
    {
        $issue = $this->issue(12);
        $service = app(IssuePlanningService::class);

        $regular = $service->saveEntry($issue, null, [
            'title' => 'Fortsetzung', 'content_type' => 'article', 'page_start' => 3, 'page_span' => 2,
        ]);
        $spread = $service->saveEntry($issue, null, [
            'title' => 'Doppelseite', 'content_type' => 'photo', 'page_start' => 6,
            'page_span' => 2, 'placement_type' => 'spread',
        ]);

        self::assertSame('normal', $regular->placement_type);
        self::assertSame('spread', $spread->placement_type);

        try {
            $service->saveEntry($issue, null, [
                'title' => 'Falsche Doppelseite', 'content_type' => 'photo', 'page_start' => 5,
                'page_span' => 2, 'placement_type' => 'spread',
            ]);
            self::fail('A true spread must begin on an even left page.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('page_start', $exception->errors());
        }
    }

    public function test_it_unplaces_without_deleting_and_respects_locked_placements(): void
    {
        $issue = $this->issue(8);
        $entry = $this->entry($issue, [
            'title' => 'Fester Comic', 'page_start' => 2, 'page_span' => 1, 'is_locked' => true,
        ]);
        $service = app(IssuePlanningService::class);

        try {
            $service->moveEntry($issue, $entry->id, 4);
            self::fail('A locked entry must not move.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('page_start', $exception->errors());
        }

        $entry->update(['is_locked' => false]);
        $service->unplaceEntry($issue, $entry->id);
        self::assertDatabaseHas('issue_entries', [
            'id' => $entry->id, 'page_start' => null, 'is_locked' => false,
        ]);
        self::assertSame(1.0, $entry->fresh()->page_span);
    }

    public function test_moving_a_linked_multi_page_entry_preserves_its_article_and_archive_assignment(): void
    {
        $issue = $this->issue(12);
        $article = Article::query()->create([
            'issue_id' => $issue->id, 'title' => 'Onlinebeitrag', 'slug' => 'onlinebeitrag',
            'category' => 'Reportage', 'status' => ArticleStatus::Published,
        ]);
        $entry = $this->entry($issue, [
            'article_id' => $article->id, 'title' => $article->title, 'page_start' => 3, 'page_span' => 2,
        ]);

        $moved = app(IssuePlanningService::class)->moveEntry($issue, $entry->id, 8);

        self::assertSame($article->id, $moved->article_id);
        self::assertSame($issue->id, $article->fresh()->issue_id);
        self::assertSame(8, $moved->page_start);
        self::assertSame($article->id, $issue->fresh()->articles()->sole()->id);
    }

    public function test_a_soft_deleted_linked_article_does_not_strand_its_planned_entry(): void
    {
        $issue = $this->issue(8);
        $article = Article::query()->create([
            'issue_id' => $issue->id, 'title' => 'Archivierter Onlinebeitrag', 'slug' => 'archivierter-onlinebeitrag',
            'category' => 'Reportage', 'status' => ArticleStatus::Draft,
        ]);
        $entry = $this->entry($issue, [
            'article_id' => $article->id, 'title' => $article->title, 'page_start' => 2,
        ]);
        $article->delete();
        $service = app(IssuePlanningService::class);

        $moved = $service->moveEntry($issue, $entry->id, 4);
        $updated = $service->saveEntry($issue, $moved, [
            ...$moved->only($moved->getFillable()), 'print_status' => 'proofing', 'author_ids' => [],
        ]);
        $unplaced = $service->unplaceEntry($issue, $updated->id);

        self::assertSame($article->id, $unplaced->article_id);
        self::assertSame('Archivierter Onlinebeitrag', $unplaced->title);
        self::assertSame('proofing', $unplaced->print_status);
        self::assertNull($unplaced->page_start);
    }

    public function test_a_linked_article_cannot_be_added_twice_and_locked_state_needs_explicit_action(): void
    {
        $issue = $this->issue(8);
        $article = Article::query()->create([
            'title' => 'Onlinebeitrag', 'slug' => 'onlinebeitrag', 'category' => 'Reportage',
            'status' => ArticleStatus::Draft,
        ]);
        $service = app(IssuePlanningService::class);
        $entry = $service->saveEntry($issue, null, [
            'article_id' => $article->id, 'content_type' => 'article', 'page_start' => 1, 'page_span' => 1,
        ]);

        try {
            $service->saveEntry($issue, null, [
                'article_id' => $article->id, 'content_type' => 'article', 'page_start' => 3, 'page_span' => 1,
            ]);
            self::fail('A linked article cannot be duplicated within the same issue.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('article_id', $exception->errors());
        }

        $locked = $service->setLocked($issue, $entry->id, true);
        try {
            $service->saveEntry($issue, $locked, [
                ...$locked->only($locked->getFillable()), 'page_start' => 2, 'author_ids' => [],
            ]);
            self::fail('A locked placement cannot be moved through the save form.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('page_start', $exception->errors());
        }
    }

    public function test_it_moves_a_planned_entry_between_issues_and_preserves_related_data(): void
    {
        $source = $this->issue(12);
        $destination = $this->issue(8);
        $article = Article::query()->create([
            'issue_id' => $source->id, 'title' => 'Verknüpfter Artikel', 'slug' => 'verknuepfter-artikel',
            'category' => 'Kommentar', 'status' => ArticleStatus::Review,
        ]);
        $entry = $this->entry($source, [
            'article_id' => $article->id, 'title' => $article->title, 'page_start' => 6, 'page_span' => 1,
            'internal_notes' => 'Für nächste Ausgabe vormerken', 'planning_status' => 'ready', 'print_status' => 'proofing',
        ]);
        $author = \App\Models\Author::query()->create(['name' => 'Ada Autorin']);
        $entry->authors()->attach($author);

        $moved = app(IssuePlanningService::class)->moveEntryToIssue($entry, $destination);

        self::assertSame($destination->id, $moved->issue_id);
        self::assertNull($moved->page_start);
        self::assertSame(1.0, $moved->page_span);
        self::assertSame('Für nächste Ausgabe vormerken', $moved->internal_notes);
        self::assertSame('proofing', $moved->print_status);
        self::assertSame([$author->id], $moved->authors()->pluck('authors.id')->all());
        self::assertSame($article->id, $moved->article_id);
        self::assertSame($source->id, $article->fresh()->issue_id);
    }

    public function test_transfer_rejects_duplicate_article_links_and_locked_entries_without_data_loss(): void
    {
        $source = $this->issue(8);
        $destination = Issue::query()->create(['title' => 'Zielausgabe 08/2026', 'page_count' => 8]);
        $article = Article::query()->create([
            'title' => 'Gemeinsamer Artikel', 'slug' => 'gemeinsamer-artikel', 'category' => 'Reportage',
            'status' => ArticleStatus::Draft,
        ]);
        $entry = $this->entry($source, ['article_id' => $article->id, 'title' => $article->title]);
        $this->entry($destination, ['article_id' => $article->id, 'title' => $article->title]);
        $service = app(IssuePlanningService::class);

        try {
            $service->moveEntryToIssue($entry, $destination);
            self::fail('An Article must only be linked once per Issue.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('issue_id', $exception->errors());
        }

        self::assertSame($source->id, $entry->fresh()->issue_id);
        $service->setLocked($source, $entry->id, true);
        try {
            $service->moveEntryToIssue($entry->fresh(), $destination);
            self::fail('Locked entries cannot be transferred.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('issue_id', $exception->errors());
        }

        self::assertSame($source->id, $entry->fresh()->issue_id);
        self::assertTrue($entry->fresh()->is_locked);
    }

    private function issue(int $pageCount): Issue
    {
        return Issue::query()->create(['title' => "Ausgabe {$pageCount}/2026", 'page_count' => $pageCount]);
    }

    private function entry(Issue $issue, array $attributes): IssueEntry
    {
        return $issue->entries()->create(array_merge([
            'title' => 'Arbeitstitel', 'content_type' => 'article', 'page_span' => 1,
            'planning_status' => 'planned', 'print_status' => 'planned',
        ], $attributes));
    }
}
