<?php

namespace Tests\Feature\IssuePlanning;

use App\Enums\ArticleStatus;
use App\Enums\UserRole;
use App\Filament\Pages\IssuePlanning;
use App\Filament\Resources\Issues\Pages\CreateIssue;
use App\Filament\Resources\Issues\IssueResource;
use App\Models\Author;
use App\Models\Article;
use App\Models\Issue;
use App\Models\IssueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Carbon\Carbon;
use Tests\TestCase;

class IssuePlanningPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_selects_the_nearest_upcoming_issue_by_default(): void
    {
        Carbon::setTestNow('2026-11-12 12:00:00');
        $editor = $this->user('redaktion');
        $current = Issue::query()->create([
            'title' => 'Aktuelle Ausgabe', 'page_count' => 8, 'is_current' => true, 'published_at' => '2026-10-01',
        ]);
        Issue::query()->create(['title' => 'Spätere Ausgabe', 'page_count' => 12, 'planned_publication_date' => '2027-04-01']);
        $next = Issue::query()->create(['title' => 'Nächste Ausgabe', 'page_count' => 8, 'planned_publication_date' => '2026-12-01']);

        try {
            $component = Livewire::actingAs($editor)->test(IssuePlanning::class);

            self::assertNotSame($current->id, $component->instance()->selectedIssueId);
            self::assertSame($next->id, $component->instance()->selectedIssueId);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_prefers_an_open_planning_issue_without_a_publication_date_to_the_current_issue(): void
    {
        $editor = $this->user('redaktion');
        Issue::query()->create([
            'title' => 'Aktuelle Ausgabe', 'page_count' => 8, 'is_current' => true,
            'published_at' => '2026-10-01', 'production_status' => 'printed',
        ]);
        $planned = Issue::query()->create([
            'title' => 'Offene geplante Ausgabe', 'page_count' => 12, 'production_status' => 'planning',
        ]);

        $component = Livewire::actingAs($editor)->test(IssuePlanning::class);

        self::assertSame($planned->id, $component->instance()->selectedIssueId);
    }

    public function test_linked_article_completion_uses_the_issue_planning_status_in_the_summary_and_flatplan(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe mit fertigem Heftplatz', 8);
        $article = $this->article($editor, 'Verknüpfter Entwurf im fertigen Heftplatz');
        $this->entry($issue, [
            'article_id' => $article->id,
            'page_start' => 2,
            'planning_status' => 'ready',
        ]);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->assertSee('1 / 1')
            ->assertSee('Redaktionell fertig')
            ->assertDontSee('Zur Freigabe');
    }

    public function test_an_unplaced_cover_placeholder_can_be_repaired_in_place(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe mit verwaistem Cover', 8);
        $cover = $issue->entries()->create([
            'title' => 'Titelseite', 'content_type' => 'cover', 'page_start' => null, 'page_span' => 1,
        ]);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->call('openCoverPlaceholder')
            ->assertSet('entryFormOpen', false)
            ->assertHasNoErrors();

        self::assertSame(1, $cover->fresh()->page_start);
        self::assertSame(1, $issue->entries()->where('content_type', 'cover')->count());
    }

    public function test_an_unplaced_structural_placeholder_can_be_deleted_explicitly(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe mit überflüssigem Cover', 8);
        $cover = $issue->entries()->create([
            'title' => 'Titelseite', 'content_type' => 'cover', 'page_start' => null, 'page_span' => 1,
        ]);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->assertSee('Platzhalter löschen')
            ->call('removeUnplacedStructuralPlaceholder', $cover->id)
            ->assertHasNoErrors();

        self::assertDatabaseMissing('issue_entries', ['id' => $cover->id]);
    }

    public function test_changing_the_issue_clears_a_stale_form_and_validation_message(): void
    {
        $editor = $this->user('redaktion');
        $firstIssue = $this->issue('Ausgabe mit vorhandenem Cover', 8);
        $secondIssue = $this->issue('Andere geplante Ausgabe', 12);
        $firstIssue->entries()->create([
            'title' => 'Titelseite', 'content_type' => 'cover', 'page_start' => 1, 'page_span' => 1,
        ]);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $firstIssue->id)
            ->call('openCoverPlaceholder')
            ->assertHasErrors('page_start')
            ->set('selectedIssueId', $secondIssue->id)
            ->assertHasNoErrors()
            ->assertSet('entryFormOpen', false)
            ->assertSee('Andere geplante Ausgabe')
            ->assertDontSee('Dieser Platzhalter ist in der Ausgabe bereits angelegt.');
    }

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

    public function test_editor_can_start_a_new_planned_issue_from_the_planner(): void
    {
        $editor = $this->user('redaktion');
        $createUrl = IssueResource::getUrl('create', ['from' => 'planning']);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->assertSee('Neue Ausgabe planen')
            ->assertSee($createUrl);

        $component = Livewire::actingAs($editor)->test(CreateIssue::class)
            ->set('from', 'planning')
            ->fillForm(['title' => 'Geplante Frühjahrsausgabe', 'page_count' => 16])
            ->call('create')
            ->assertHasNoErrors();

        $issue = Issue::query()->where('title', 'Geplante Frühjahrsausgabe')->sole();
        $component->assertRedirect(IssuePlanning::getUrl(['issue' => $issue->id]));
        self::assertSame('planning', $issue->production_status);
    }

    public function test_cover_is_a_placeholder_and_a_generic_closing_page_can_be_added(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe mit Cover und Schlussseite', 8);
        $component = Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->assertSee('Titelseite als Platzhalter anlegen')
            ->assertSee('Schlussseite als Platzhalter einplanen')
            ->call('openCoverPlaceholder')
            ->assertSet('entryForm.article_id', '')
            ->assertSet('entryForm.title', 'Titelseite')
            ->assertSet('entryForm.content_type', 'cover')
            ->assertSet('entryForm.page_start', 1)
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->call('openClosingPagePlaceholder')
            ->assertSet('entryForm.article_id', '')
            ->assertSet('entryForm.title', 'Schlussseite')
            ->assertSet('entryForm.content_type', 'closing_page')
            ->assertSet('entryForm.page_start', 8)
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->assertSee('Titelseite')
            ->assertSee('Schlussseite');

        self::assertDatabaseHas('issue_entries', [
            'issue_id' => $issue->id, 'content_type' => 'cover', 'title' => 'Titelseite', 'page_start' => 1,
        ]);
        self::assertDatabaseHas('issue_entries', [
            'issue_id' => $issue->id, 'content_type' => 'closing_page', 'title' => 'Schlussseite', 'page_start' => 8,
        ]);
    }

    public function test_article_picker_only_offers_unlinked_articles_and_the_current_link_when_editing(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe Auswahl', 8);
        $otherIssue = $this->issue('Andere Ausgabe', 8);
        $available = $this->article($editor, 'Freier Artikel');
        $linkedHere = $this->article($editor, 'Artikel dieser Ausgabe');
        $linkedElsewhere = $this->article($editor, 'Artikel anderer Ausgabe');
        $currentEntry = $this->entry($issue, ['article_id' => $linkedHere->id, 'title' => $linkedHere->title]);
        $this->entry($otherIssue, ['article_id' => $linkedElsewhere->id, 'title' => $linkedElsewhere->title]);
        $component = Livewire::actingAs($editor)->test(IssuePlanning::class)->call('selectIssue', $issue->id);

        self::assertSame([$available->id], array_map('intval', array_keys($component->instance()->articleOptions())));

        $component->call('openEditEntry', $currentEntry->id);
        self::assertEqualsCanonicalizing(
            [$available->id, $linkedHere->id],
            array_map('intval', array_keys($component->instance()->articleOptions())),
        );
    }

    public function test_selecting_an_article_copies_its_title_type_and_authors_into_the_plan(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe Artikelmetadaten', 8);
        $article = $this->article($editor);
        $author = Author::query()->create(['name' => 'Ada Beispiel']);
        $article->authors()->attach($author);

        $component = Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->call('openEntryForm', 3)
            ->set('entryForm.article_id', $article->id)
            ->assertSet('entryForm.title', $article->title)
            ->assertSet('entryForm.content_type', 'article')
            ->assertSet('entryForm.author_ids', [$author->id])
            ->assertSee('Rubrik: Interview')
            ->assertSee('Ada Beispiel')
            ->call('saveEntry')
            ->assertHasNoErrors();

        $entry = $issue->entries()->sole();
        self::assertSame('article', $entry->content_type);
        self::assertSame([$author->id], $entry->display_authors->modelKeys());
        self::assertSame(0, $entry->authors()->count());
    }

    public function test_responsible_user_picker_includes_editorial_and_author_accounts(): void
    {
        $editor = $this->user('redaktion');
        $author = $this->user('redakteur', 'author@example.test');
        $admin = $this->user('admin', 'admin@example.test');
        $component = Livewire::actingAs($editor)->test(IssuePlanning::class);
        $options = $component->instance()->responsibleUserOptions();

        self::assertSame($author->name, $options[$author->id]);
        self::assertSame($editor->name, $options[$editor->id]);
        self::assertSame($admin->name, $options[$admin->id]);
    }

    public function test_page_offset_label_explains_how_to_share_a_page(): void
    {
        $editor = $this->user('redaktion');
        $issue = $this->issue('Ausgabe Seitenanteil', 8);

        Livewire::actingAs($editor)->test(IssuePlanning::class)
            ->call('selectIssue', $issue->id)
            ->call('openEntryForm', 3)
            ->assertSee('Startposition auf der Seite')
            ->assertSee('0,5 = Mitte')
            ->assertSeeHtml('step="0.25"')
            ->assertSeeHtml('max="0.75"');
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
            ->assertSee('Recherche')
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

    private function article(User $creator, string $title = 'Verknüpfter Artikel'): Article
    {
        return Article::query()->create([
            'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title), 'category' => 'Interview',
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
