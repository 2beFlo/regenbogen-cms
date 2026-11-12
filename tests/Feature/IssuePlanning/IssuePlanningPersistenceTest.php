<?php

namespace Tests\Feature\IssuePlanning;

use App\Enums\ArticleStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\Author;
use App\Models\Issue;
use App\Models\IssueEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuePlanningPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_placeholder_is_persisted_without_creating_or_changing_an_article(): void
    {
        $issue = $this->issue();

        $entry = $issue->entries()->create([
            'title' => 'Interview mit XY',
            'content_type' => 'interview',
            'page_span' => 1,
            'planning_status' => 'research',
            'print_status' => 'planned',
        ]);

        self::assertNull($entry->article_id);
        self::assertSame('Interview mit XY', $entry->display_title);
        self::assertSame(1, $issue->entries()->count());
        self::assertSame(0, $issue->articles()->count());
    }

    public function test_a_linked_entry_uses_the_article_as_its_display_source_and_keeps_archive_relation(): void
    {
        $issue = $this->issue();
        $article = $this->article($issue);
        $author = Author::query()->create(['name' => 'Ada Redaktion']);
        $article->authors()->attach($author);

        $entry = $issue->entries()->create([
            'article_id' => $article->id,
            'title' => $article->title,
            'content_type' => 'article',
            'page_start' => 2,
            'page_span' => 1,
            'planning_status' => 'planned',
            'print_status' => 'in_layout',
        ]);

        self::assertSame($article->title, $entry->display_title);
        self::assertSame(ArticleStatus::Review, $entry->article->status);
        self::assertSame(['Ada Redaktion'], $entry->display_authors->pluck('name')->all());
        self::assertSame($article->id, $issue->articles()->sole()->id);
    }

    public function test_placeholder_entries_can_have_multiple_existing_authors(): void
    {
        $entry = $this->issue()->entries()->create([
            'title' => 'Interview mit XY',
            'content_type' => 'interview',
            'page_span' => 1,
            'planning_status' => 'idea',
            'print_status' => 'planned',
        ]);
        $authors = collect(['Ada Redaktion', 'Bert Text'])->map(
            fn (string $name) => Author::query()->create(['name' => $name])
        );

        $authorIds = $authors->pluck('id')->all();
        $entry->authors()->attach($authorIds);

        self::assertEqualsCanonicalizing($authorIds, $entry->authors()->pluck('authors.id')->all());
    }

    public function test_an_article_can_be_linked_only_once_per_issue(): void
    {
        $issue = $this->issue();
        $article = $this->article($issue);
        $attributes = [
            'article_id' => $article->id,
            'title' => $article->title,
            'content_type' => 'article',
            'page_span' => 1,
            'planning_status' => 'planned',
            'print_status' => 'planned',
        ];

        $issue->entries()->create($attributes);

        $this->expectException(QueryException::class);
        $issue->entries()->create($attributes);
    }

    public function test_an_author_profile_can_be_explicitly_linked_to_at_most_one_user(): void
    {
        $user = User::query()->create([
            'name' => 'Ada Redaktion',
            'email' => 'ada@example.test',
            'password' => 'test-password',
            'role' => UserRole::Redakteur,
        ]);
        $author = Author::query()->create(['name' => 'Ada Redaktion', 'user_id' => $user->id]);

        self::assertSame($user->id, $author->user->id);

        $this->expectException(QueryException::class);
        Author::query()->create(['name' => 'Ada Zweitprofil', 'user_id' => $user->id]);
    }

    private function issue(): Issue
    {
        return Issue::query()->create(['title' => 'Ausgabe 03/2026', 'page_count' => 22]);
    }

    private function article(Issue $issue): Article
    {
        return Article::query()->create([
            'issue_id' => $issue->id,
            'title' => 'Ein geplanter Onlineartikel',
            'slug' => 'ein-geplanter-onlineartikel',
            'category' => 'Interview',
            'status' => ArticleStatus::Review,
        ]);
    }
}
