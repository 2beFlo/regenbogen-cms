<?php

namespace Tests\Feature\IssuePlanning;

use App\Enums\ArticleStatus;
use App\Enums\UserRole;
use App\Filament\Pages\IssuePlanning;
use App\Models\Article;
use App\Models\Issue;
use App\Models\IssueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IssuePlanningPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_dynamic_odd_and_even_spreads_and_free_pages(): void
    {
        $editor = $this->user('redaktion');
        $oddIssue = $this->issue('Ungrade Ausgabe', 5);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->assertSee('Seite 1')
            ->assertSee('Seite 5')
            ->assertSee('Frei');

        $evenIssue = $this->issue('Gerade Ausgabe', 4);
        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $evenIssue->id)
            ->assertSee('Seite 4')
            ->assertSee('Seite 3');

        foreach ([8, 12, 16, 22] as $pageCount) {
            $issue = $this->issue("Ausgabe {$pageCount}", $pageCount);
            Livewire::actingAs($editor)->test(IssuePlanning::class)
                ->call('selectIssue', $issue->id)
                ->assertSee('Seite 1')
                ->assertSee("Seite {$pageCount}");
        }
    }

    public function test_it_counts_unplaced_reservations_and_switches_to_the_list_view(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe Liste', 8);
        $this->entry($issue, ['title' => 'Noch ohne Position', 'page_span' => 2]);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->assertSee('Noch nicht platziert')
            ->set('viewMode', 'list')
            ->assertSee('Liste')
            ->assertSee('Alle Heftplätze')
            ->assertSee('Noch ohne Position');
    }

    public function test_editor_can_create_a_placeholder_link_an_article_and_change_both_statuses(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe Neu', 12);
        $article = $this->article($editor);

        $component = Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->call('openEntryForm', 3)
            ->set('entryForm.title', 'Interview mit XY')
            ->set('entryForm.content_type', 'interview')
            ->set('entryForm.page_span', 1)
            ->set('entryForm.planning_status', 'research')
            ->set('entryForm.print_status', 'planned')
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->assertSee('Interview mit XY');

        $placeholder = IssueEntry::query()->where('issue_id', $issue->id)->sole();
        self::assertNull($placeholder->article_id);
        self::assertSame(3, $placeholder->page_start);

        $component->call('openEditEntry', $placeholder->id)
            ->set('entryForm.article_id', $article->id)
            ->set('entryForm.print_status', 'layout_ready')
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->assertSee('Verknüpfter Artikel')
            ->assertSee('Zur Freigabe')
            ->assertSee('Gesetzte Fassung vorhanden')
            ->assertSee('/admin/articles/'.$article->id.'/edit');

        self::assertSame(1, $issue->entries()->count());
        self::assertDatabaseHas('issue_entries', [
            'id' => $placeholder->id, 'issue_id' => $issue->id, 'article_id' => $article->id,
            'title' => $article->title, 'page_start' => 3, 'print_status' => 'layout_ready',
        ]);
        self::assertNull($article->fresh()->issue_id);
    }

    public function test_editor_can_move_and_unplace_entries_without_losing_them(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe Verschieben', 10);
        $entry = $this->entry($issue, ['title' => 'Mehrseitiger Text', 'page_start' => 2, 'page_span' => 2]);

        $component = Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->call('moveEntryToPage', $entry->id, 6, 0)
            ->assertHasNoErrors()
            ->assertSee('Fortsetzung');

        self::assertSame(6, $entry->fresh()->page_start);
        $component->call('unplaceEntry', $entry->id)->assertHasNoErrors();
        self::assertDatabaseHas('issue_entries', ['id' => $entry->id, 'page_start' => null, 'page_span' => 2]);
    }

    public function test_a_collision_is_reported_without_changing_either_entry(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe Konflikt', 8);
        $first = $this->entry($issue, ['title' => 'Erster Text', 'page_start' => 2, 'page_span' => 0.5]);
        $second = $this->entry($issue, ['title' => 'Zweiter Text', 'page_start' => 5, 'page_span' => 1]);

        try {
            Livewire::actingAs($editor)->test(IssuePlanning::class)
                ->call('selectIssue', $issue->id)
                ->call('moveEntryToPage', $second->id, 2, 0.25)
                ->assertHasErrors('page_start');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        self::assertSame(2, $first->fresh()->page_start);
        self::assertSame(5, $second->fresh()->page_start);
    }

    public function test_editor_can_transfer_a_contribution_to_another_issue_from_the_list(): void
    {
        $editor = $this->user('redaktion');
        $source = $this->issue('Ausgabe Quelle', 8);
        $destination = $this->issue('Ausgabe Ziel', 12);
        $entry = $this->entry($source, [
            'title' => 'Verschobener Beitrag', 'page_start' => 4, 'page_span' => 1.5,
            'internal_notes' => 'Absprachen bleiben erhalten',
        ]);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $source->id)
            ->set('viewMode', 'list')
            ->set("transferTarget.{$entry->id}", $destination->id)
            ->call('transferEntryToIssue', $entry->id)
            ->assertHasNoErrors();

        self::assertSame($destination->id, $entry->fresh()->issue_id);
        self::assertNull($entry->fresh()->page_start);
        self::assertSame('Absprachen bleiben erhalten', $entry->fresh()->internal_notes);
        self::assertSame(1.5, $entry->fresh()->page_span);
    }

    public function test_authors_see_their_entries_without_editor_controls_or_internal_notes(): void
    {
        $author = $this->user('redakteur');
        $other = $this->user('redakteur', 'other@example.test');
        $issue = $this->issue('Ausgabe Autor', 8);
        $own = $this->entry($issue, ['title' => 'Eigener Text', 'internal_notes' => 'Nur Redaktion', 'responsible_user_id' => $author->id]);
        $this->entry($issue, ['title' => 'Fremder Text', 'internal_notes' => 'Fremde Notiz', 'responsible_user_id' => $other->id]);

        $component = Livewire::actingAs($author)->test(IssuePlanning::class)
            ->assertSee('Eigener Text')
            ->assertDontSee('Fremder Text')
            ->assertDontSee('Nur Redaktion')
            ->assertDontSee('Beitrag einplanen')
            ->assertDontSee('Eintrag bearbeiten');

        self::assertArrayNotHasKey('internal_notes', $component->instance()->entries()->first()->getAttributes());
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::query()->create([
            'name' => 'Redaktion '.$role,
            'email' => $email ?? $role.'@example.test',
            'password' => 'test-password',
            'role' => UserRole::from($role),
        ]);
    }

    private function issue(string $title, int $pageCount): Issue
    {
        return Issue::query()->create(['title' => $title, 'page_count' => $pageCount]);
    }

    private function article(User $creator): Article
    {
        return Article::query()->create([
            'title' => 'Verknüpfter Artikel', 'slug' => 'verknuepfter-artikel', 'category' => 'Interview',
            'created_by' => $creator->id, 'status' => ArticleStatus::Review,
        ]);
    }

    private function entry(Issue $issue, array $overrides): IssueEntry
    {
        return $issue->entries()->create(array_merge([
            'title' => 'Arbeitstitel', 'content_type' => 'article', 'page_span' => 1,
            'planning_status' => 'planned', 'print_status' => 'planned', 'internal_notes' => 'Interne Notiz',
        ], $overrides));
    }
}
