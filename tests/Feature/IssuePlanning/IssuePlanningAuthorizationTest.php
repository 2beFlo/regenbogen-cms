<?php

namespace Tests\Feature\IssuePlanning;

use App\Enums\UserRole;
use App\Enums\ArticleStatus;
use App\Filament\Resources\Issues\IssueResource;
use App\Models\Article;
use App\Models\Author;
use App\Models\Issue;
use App\Models\User;
use App\Support\IssuePlanning\IssuePlanningAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class IssuePlanningAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_editorial_users_can_view_all_entries_and_internal_notes(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue();
        $first = $this->entry($issue, 'Alle sehen dies', 'Notiz intern');
        $this->entry($issue, 'Auch dieser Beitrag', 'Zweite interne Notiz');
        $access = app(IssuePlanningAccess::class);

        self::assertTrue($access->canAccess($editor));
        self::assertTrue($access->canMutate($editor));
        self::assertTrue($access->canSeeInternalNotes($editor));
        self::assertTrue($access->canViewIssue($editor, $issue));
        self::assertCount(2, $access->visibleEntries($editor, $issue));
        self::assertSame('Notiz intern', $access->visibleEntries($editor, $issue)->firstWhere('id', $first->id)->internal_notes);
    }

    public function test_authors_can_view_only_entries_assigned_by_responsible_user_or_profile_link(): void
    {
        $author = $this->user('redakteur');
        $other = $this->user('redakteur', 'other@example.test');
        $issue = $this->issue();
        $ownByUser = $this->entry($issue, 'Direkt zugeordnet', 'Geheim', ['responsible_user_id' => $author->id]);
        $ownByProfile = $this->entry($issue, 'Profil zugeordnet', 'Nicht anzeigen');
        $profile = Author::query()->create(['name' => $author->name, 'user_id' => $author->id]);
        $ownByProfile->authors()->attach($profile);
        $createdArticle = Article::query()->create([
            'title' => 'Eigener Entwurf', 'slug' => 'eigener-entwurf', 'category' => 'Kommentar',
            'created_by' => $author->id, 'status' => ArticleStatus::Draft,
        ]);
        $ownByArticle = $this->entry($issue, 'Eigener Artikel', 'Interne Notiz', ['article_id' => $createdArticle->id]);
        $profileArticle = Article::query()->create([
            'title' => 'Beitrag mit Profil', 'slug' => 'beitrag-mit-profil', 'category' => 'Interview',
            'status' => ArticleStatus::Draft,
        ]);
        $profileArticle->authors()->attach($profile);
        $ownByLinkedProfile = $this->entry($issue, 'Profilartikel', 'Interne Notiz', ['article_id' => $profileArticle->id]);
        $this->entry($issue, 'Fremder Beitrag', 'Fremde Notiz', ['responsible_user_id' => $other->id]);
        $access = app(IssuePlanningAccess::class);

        self::assertTrue($access->canAccess($author));
        self::assertFalse($access->canMutate($author));
        self::assertFalse($access->canSeeInternalNotes($author));
        self::assertTrue($access->canViewIssue($author, $issue));
        $visible = $access->visibleEntries($author, $issue);
        self::assertEqualsCanonicalizing([
            $ownByUser->id, $ownByProfile->id, $ownByArticle->id, $ownByLinkedProfile->id,
        ], $visible->modelKeys());
        self::assertArrayNotHasKey('internal_notes', $visible->first()->getAttributes());
        self::assertSame(1, $access->visibleIssues($author)->count());
    }

    public function test_authors_cannot_open_issues_without_an_own_entry_or_mutate_plans(): void
    {
        $author = $this->user('redakteur');
        $issue = $this->issue();
        $this->entry($issue, 'Nur Redaktion', 'intern');
        $access = app(IssuePlanningAccess::class);

        self::assertFalse($access->canViewIssue($author, $issue));
        self::assertCount(0, $access->visibleIssues($author));
        self::assertCount(0, $access->visibleEntries($author, $issue));
        self::assertFalse($access->canMutate($author));
        self::assertFalse($access->canAccess(null));
    }

    public function test_public_pages_do_not_expose_issue_planning(): void
    {
        $this->get('/ausgabenplanung')->assertNotFound();
        $this->get('/issue-planning')->assertNotFound();
        $this->get('/admin/issue-planning')->assertRedirect('/admin/login');

        $this->actingAs($this->user('redakteur'))
            ->get('/admin/issue-planning')
            ->assertOk()
            ->assertSee('Ausgabenplanung');
    }

    public function test_an_issue_with_a_plan_cannot_be_deleted_through_the_cms_or_database(): void
    {
        $this->actingAs($this->user('redaktion'));
        $issue = $this->issue();
        $this->entry($issue, 'Geplanter Beitrag', 'Redaktionelle Notiz');

        self::assertFalse(IssueResource::canDelete($issue));
        try {
            $issue->delete();
            self::fail('A database constraint must protect planning records from issue deletion.');
        } catch (QueryException) {
            self::assertDatabaseHas('issue_entries', ['issue_id' => $issue->id, 'title' => 'Geplanter Beitrag']);
        }

        $emptyIssue = Issue::query()->create(['title' => 'Leere Ausgabe', 'page_count' => 16]);
        self::assertTrue(IssueResource::canDelete($emptyIssue));
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::query()->create([
            'name' => $email ? 'Andere Autorin' : 'Autorin',
            'email' => $email ?? 'author@example.test',
            'password' => 'test-password',
            'role' => UserRole::from($role),
        ]);
    }

    private function issue(): Issue
    {
        return Issue::query()->create(['title' => 'Ausgabe 02/2026', 'page_count' => 16]);
    }

    private function entry(Issue $issue, string $title, string $notes, array $attributes = []): \App\Models\IssueEntry
    {
        return $issue->entries()->create(array_merge([
            'title' => $title, 'content_type' => 'article', 'page_span' => 1,
            'planning_status' => 'planned', 'print_status' => 'planned', 'internal_notes' => $notes,
        ], $attributes));
    }
}
