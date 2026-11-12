# REGENBOGEN Ausgabenplanung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a private, role-aware print-issue planner with a responsive visual flatplan, unplaced assignments, fractional page capacity, and an independent print-production workflow.

**Architecture:** Extend the existing `Issue` model and resource with planning metadata. Add `IssueEntry` as the separate planned print placement, optionally linked to an existing CMS `Article`; calculate page spreads, page segments, conflicts, and capacity in a pure calculator, and serialize all mutations through an issue-scoped transactional service. A Filament page provides the board, list view, forms, and read-only author view; no public route is added.

**Tech Stack:** Laravel 13, PHP 8.5, Filament 5, Livewire 4, Blade, existing SQLite local database / MySQL-compatible production migrations, PHPUnit 12 as a development-only test runner.

**Spec:** User-supplied development assignment at `/Users/florianlillpopp/.codex/attachments/2099595c-431e-4282-b6f7-c37696873e9a/Eingefügter Text.txt`.

## Global Constraints

- Keep the existing `Issue` model as the one Ausgabe model; do not add a second issue entity.
- Keep the existing Article editorial workflow as the source of truth for linked articles.
- Keep print-production state separate from Article status and online publication.
- Store every positive page count supported by the existing unsigned-smallint field; odd counts are valid and no print-signature multiple is enforced.
- Treat page shrink conflicts as validation errors; never delete or rewrite entries, assignments, or files implicitly.
- Make page positions, reserved extents, collision detection, and capacity calculations server-authoritative and transactional.
- Editorial users may plan; authors may only view their own placements; internal notes and planning data remain private.
- Add no public route, PDF automation, Affinity integration, or automatic online publication.
- Prefer existing Laravel/Filament components and add no runtime dependency.
- Use PHPUnit only as a development dependency because the repository has no test suite and the assignment requires core-function tests.

## Review Focus

- Odd and even page counts, including a final single left page and arbitrary counts such as 4, 8, 12, 16, 22, and 32.
- Fractional entries sharing a page, arbitrary decimal extents, multi-page continuation, true facing-page spreads, and overlapping placements.
- Reducing page count below saved placements must fail while preserving every entry and its links.
- Concurrent placement changes must serialize on the Issue record and never overwrite another entry.
- Author access must expose only that author's placements and no internal notes; non-editorial roles must not mutate planning.
- No unauthenticated public route may reveal issue plans, entries, or notes; existing public issue/article output must remain stable.

---

### Task 1: Test runner and flatplan calculations

**Files:**
- Create: `phpunit.xml`
- Create: `tests/TestCase.php`
- Create: `tests/Unit/IssuePlanning/FlatplanCalculatorTest.php`
- Create: `app/Support/IssuePlanning/FlatplanCalculator.php`
- Modify: `composer.json`, `composer.lock`

**Interfaces:**
- Produce `FlatplanCalculator::spreads(int $pageCount): array` with each spread shaped as `['left' => ?int, 'right' => ?int]`.
- Produce `FlatplanCalculator::segments(int $pageCount, iterable $entries): array`, keyed by page number and containing each entry's page-local `offset` and `extent`.
- Produce `FlatplanCalculator::conflicts(int $pageCount, iterable $entries): array` with entry ID, page number, and conflict reason.
- Produce `FlatplanCalculator::summarize(int $pageCount, iterable $entries): array` with total, placed, unplaced-reserved, remaining, overbooked, editorial-ready, and print-ready counts.
- Entry input uses `id`, nullable `page_start`, `page_offset` in `[0,1)`, positive `page_span`, `placement_type` (`normal` or `spread`), `planning_status`, `print_status`, and linked `article_status` where present.
- A regular entry may have any positive decimal span; a true spread has span `2`, offset `0`, and starts on an even left page. Placeholder statuses are `idea`, `planned`, `research`, `writing`, `ready`; print statuses are `planned`, `ready_for_layout`, `in_layout`, `layout_ready`, `proofing`, `approved`.

- [ ] Add PHPUnit 12 as a development-only dependency, configure an in-memory SQLite test environment, and add a Laravel `TestCase`.
- [ ] Write failing tests for page spreads with counts 1, 4, 5, 8, 22, and 32; page 1 is right, later pairs are 2–3 / 4–5, and an even final page is alone on the left.
- [ ] Write failing tests for half-page and arbitrary decimal entries sharing a page, multi-page segments, overlapping segments, out-of-range placement, and a true spread requiring two facing pages.
- [ ] Write failing tests for capacity distinguishing placed pages, unplaced reserved pages, free pages, and overbooking.
- [ ] Run `vendor/bin/phpunit tests/Unit/IssuePlanning/FlatplanCalculatorTest.php`; confirm the missing calculator causes the expected failures.
- [ ] Implement only the pure calculations required by those tests.
- [ ] Run the focused test file, then `vendor/bin/phpunit`; both must pass.
- [ ] Commit as `feat: add flatplan calculation rules`.

### Task 2: Issue-planning schema and domain models

**Files:**
- Create: `database/migrations/*_add_planning_fields_to_issues.php`
- Create: `database/migrations/*_create_issue_entries_table.php`
- Create: `database/migrations/*_link_authors_to_users.php`
- Create: `app/Models/IssueEntry.php`
- Modify: `app/Models/Issue.php`, `app/Models/Article.php`, `app/Models/Author.php`, `app/Filament/Resources/Authors/AuthorResource.php`
- Create: `tests/Feature/IssuePlanning/IssuePlanningPersistenceTest.php`

**Interfaces:**
- Extend `issues` with work title/theme, planned publication date, editorial deadline, print date, issue production status, and internal planning notes. Keep existing `page_count`, public PDF, publication date, and current-issue behavior.
- `IssueEntry` stores nullable `article_id`, nullable placeholder title, content type, nullable page start, decimal page offset/span, normal/spread placement type, lock flag, placeholder planning status, independent print status, responsible user, due date, and internal notes.
- Add `issue_entry_author` so placeholders can reuse existing `Author` records. Linked entries display Article title/authors/status from the Article relationship rather than asking editors to re-enter them.
- Add nullable unique `authors.user_id` so a `Redakteur` account can be explicitly linked to its Author profile; do not infer identity from matching names or emails.
- Add unique `(issue_id, article_id)` for non-null linked articles. Keep the existing `articles.issue_id` public archive association unchanged.

- [ ] Write failing persistence tests proving existing Issue/article relationships remain intact, placeholders persist without articles, linked entries derive Article data, multiple Authors attach, distinct entries cannot link the same Article twice in one Issue, and an Author can link to at most one User.
- [ ] Run the focused test file and confirm failure on missing schema/models.
- [ ] Add forward-only migrations and relationships; use nullable columns where legacy Issue rows may not have planning data.
- [ ] Run the focused persistence tests and the full suite.
- [ ] Commit as `feat: add issue planning records`.

### Task 3: Transactional planning operations and page-count safety

**Files:**
- Create: `app/Services/IssuePlanningService.php`
- Modify: `app/Models/Issue.php`, `app/Models/IssueEntry.php`
- Create: `tests/Feature/IssuePlanning/IssuePlanningServiceTest.php`

**Interfaces:**
- `IssuePlanningService::saveEntry(Issue $issue, ?IssueEntry $entry, array $data): IssueEntry` creates or updates a placeholder/linked entry, validates server-side, and syncs placeholder authors.
- `IssuePlanningService::moveEntry(Issue $issue, int $entryId, int $pageStart, float $pageOffset = 0): IssueEntry` moves a whole entry and rejects locked entries, page overflow, invalid spreads, and collisions.
- `IssuePlanningService::unplaceEntry(Issue $issue, int $entryId): IssueEntry` clears only placement coordinates; reserved extent and entry data remain.
- `IssuePlanningService::moveEntryToIssue(IssueEntry $entry, Issue $destination): IssueEntry` transfers an entry to the destination's unplaced shelf while preserving its article link, authors, extent, statuses, due date, and notes.
- `IssuePlanningService::assertPageCountChange(Issue $issue, int $newPageCount): void` rejects shrinking below any occupied page without deleting data.
- Every mutation uses a DB transaction and locks the Issue and its entries before validating the resulting full plan.

- [ ] Write failing tests for supported page counts including odd values, unsized entries blocking or allowing resize as appropriate, page shrink preserving entries, collision rejection, and stale/concurrent-safe moves.
- [ ] Write failing tests for regular two-page content versus a double-page spread (span 2, starts on an even left page, offset 0), lock behavior, unplacing without deleting, and linked Article preservation.
- [ ] Write failing tests for cross-issue transfers, duplicate article links in the target issue, and preservation of notes/authors/print metadata.
- [ ] Run the focused service test file and confirm failures occur on missing operations.
- [ ] Implement transaction-scoped operations using the calculator from Task 1.
- [ ] Run the focused test file and the full suite.
- [ ] Commit as `feat: validate and save print placements`.

### Task 4: Ausgabe form, planning access, and Filament page shell

**Files:**
- Create: `app/Support/IssuePlanning/IssuePlanningAccess.php`
- Create: `app/Filament/Pages/IssuePlanning.php`
- Modify: `app/Filament/Resources/Issues/IssueResource.php`, `app/Filament/Resources/Issues/Pages/EditIssue.php`, `app/Providers/Filament/AdminPanelProvider.php`
- Create: `resources/views/filament/pages/issue-planning.blade.php`
- Create: `tests/Feature/IssuePlanning/IssuePlanningAuthorizationTest.php`

**Interfaces:**
- Extend existing Ausgabe create/edit forms with planning metadata and dynamic page count.
- Redakteur/Administration can see and mutate all plans. `Redakteur` (author role) can view only entries connected by assigned responsible user, their `Article.created_by`, or an explicitly linked `Author.user_id`; they cannot mutate or see internal notes.
- `IssuePlanning::canAccess()` delegates to `IssuePlanningAccess`; there are no routes outside Filament.
- The page lists existing Issues and opens the selected Issue plan; issue creation/edit remains in `IssueResource`.

- [ ] Write failing tests for editorial access, author-own-entry read access, other-author denial, non-editor mutation denial, internal note visibility, and public endpoint absence.
- [ ] Add failing validation tests for page-count shrink through the existing Issue edit path.
- [ ] Run the focused authorization tests and confirm they fail against the current page/route structure.
- [ ] Implement the Issue resource fields, guarded resize, page shell, and access service; avoid exposing author-visible notes.
- [ ] Run the focused authorization tests and full suite.
- [ ] Commit as `feat: add role-aware issue planning page`.

### Task 5: Flatplan board, list view, entry forms, and capacity display

**Files:**
- Modify: `app/Filament/Pages/IssuePlanning.php`, `resources/views/filament/pages/issue-planning.blade.php`
- Create: `resources/views/filament/pages/partials/issue-plan-spreads.blade.php`
- Create: `resources/views/filament/pages/partials/issue-plan-list.blade.php`
- Modify: `public/css/regenbogen-admin.css`
- Create: `tests/Feature/IssuePlanning/IssuePlanningPageTest.php`

**Interfaces:**
- Board is built from `FlatplanCalculator::spreads()` and `segments()`; page cards use A5 portrait proportions and display page number, title, content type, reserved extent, author/responsible person, editorial status, and print status.
- Unplaced entries appear in a distinct shelf; reserved extent remains counted while no page start exists.
- Editorial users get Filament forms/actions to create placeholders, link existing Articles, set extent/status/notes/people, place/unplace, edit, and lock/unlock entries.
- The board supports HTML drag/drop. Manual page/offset controls provide the equivalent operation without drag/drop.
- A switchable table/list view shows all entries and placement metadata; mobile defaults to list and retains manual placement controls.
- A summary shows total, placed, unplaced-reserved, free/overbooked, editorial-ready, and print-ready pages.

- [ ] Write failing page tests for rendering odd/even plans, empty/free pages, unplaced reservations, capacity summary, board/list toggle, and linked Article edit URL.
- [ ] Write failing interaction tests for creating a placeholder, linking an existing Article, moving/unplacing, changing statuses, and rejecting a collision without overwriting.
- [ ] Run the focused page tests and confirm expected failures.
- [ ] Implement the board/list Blade views and server actions with existing Filament styles and the transactional service.
- [ ] Run focused tests and the full suite; confirm editor-only controls are absent for authors.
- [ ] Commit as `feat: build responsive issue flatplan`.

### Task 6: Regression review and operating documentation

**Files:**
- Modify: `README.md`, `REDAKTIONSHANDBUCH.md`, `app/Console/Commands/QualityCheckRegenbogen.php`
- Add focused tests where review identifies uncovered core behavior.

- [ ] Document issue setup, page-count changes, placeholders, linking, double spreads, drag/drop/manual placement, role visibility, and separate print status.
- [ ] Extend the quality command only with deterministic schema/config checks relevant to issue planning; do not add live-content assumptions.
- [ ] Run focused PHPUnit tests, the full PHPUnit suite, `php artisan regenbogen:quality --strict`, `php artisan route:list`, migration checks, and PHP syntax checks.
- [ ] Run an isolated headless browser check against a local test account for desktop board/list and narrow mobile fallback; use synthetic local records only and clean them up.
- [ ] Review the full branch diff against the supplied assignment; preserve public issue/article pages and verify no public planning route exists.
- [ ] Commit as `docs: document Ausgabeplanung workflow`.

## Deferred from v1

- Affinity Publisher integration, PDF parsing/page mapping, PDF comments, and automatic typesetting.
- A public table of contents or automatic public output from planning data.
- Per-page layout preview uploads. This requires a persisted page-asset model; add it only after the core plan view is stable and if it remains a small isolated extension.

## Execution choice

Implement inline in this feature branch because the calculation, service, Livewire actions, and authorization share interfaces that benefit from one continuous TDD loop. Use one fresh whole-branch review before integration.
