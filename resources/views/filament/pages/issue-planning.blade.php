<x-filament::page>
    @php($issues = $this->issues())
    @php($issue = $this->selectedIssue())
    @php($entries = $this->entries()->keyBy('id'))
    @php($transferTargets = $this->canMutatePlan() && $viewMode === 'list' ? $this->transferTargets() : collect())
    @php($segments = $this->segments())
    @php($summary = $this->summary())

    <div class="regenbogen-issue-planning" x-data x-init="if (window.matchMedia('(max-width: 700px)').matches) $wire.set('viewMode', 'list')">
        @if ($issues->isEmpty())
            <x-filament::section>
                <div class="regenbogen-issue-planning__empty">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Es gibt noch keine Ausgaben, die du ansehen kannst.</p>
                    @if ($this->canMutatePlan())
                        <a href="{{ \App\Filament\Resources\Issues\IssueResource::getUrl('create', ['from' => 'planning']) }}" class="fi-btn fi-btn-size-md fi-btn-color-primary">Neue Ausgabe planen</a>
                    @endif
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="regenbogen-issue-planning__toolbar">
                    <label class="block w-full sm:max-w-md">
                        <span class="mb-1 block text-sm font-medium">Ausgabe</span>
                        <select wire:model.live="selectedIssueId" class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5">
                            @foreach ($issues as $availableIssue)
                                <option value="{{ $availableIssue->id }}">{{ $availableIssue->issue_code ?: $availableIssue->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="regenbogen-issue-planning__toolbar-actions">
                        @if ($this->canMutatePlan())
                            <a href="{{ \App\Filament\Resources\Issues\IssueResource::getUrl('create', ['from' => 'planning']) }}" class="fi-btn fi-btn-size-md fi-btn-color-gray">Neue Ausgabe planen</a>
                        @endif
                        @if ($this->canMutatePlan() && $issue)
                            <a href="{{ \App\Filament\Resources\Issues\IssueResource::getUrl('edit', ['record' => $issue]) }}" class="fi-btn fi-btn-size-md fi-btn-color-gray">Ausgabe bearbeiten</a>
                            <button type="button" wire:click="openEntryForm" class="fi-btn fi-btn-size-md fi-btn-color-primary">Beitrag einplanen</button>
                        @endif
                    </div>
                </div>
            </x-filament::section>

            @if ($issue)
                <x-filament::section>
                    <div class="regenbogen-issue-planning__issue-head">
                        <div>
                            <h2>{{ $issue->work_title ?: $issue->title }}</h2>
                            <p>
                                {{ $issue->issue_code ? $issue->issue_code.' · ' : '' }}{{ $issue->page_count ?: 'Noch keine Seitenzahl' }} Seiten
                                @if ($issue->planned_publication_date) · geplant für {{ $issue->planned_publication_date->format('d.m.Y') }} @endif
                                @if ($this->canMutatePlan()) · {{ $this->issueProductionStatusLabel($issue) }} @endif
                            </p>
                        </div>
                        <div class="regenbogen-issue-planning__view-switch" role="group" aria-label="Ansicht wählen">
                            <button type="button" wire:click="$set('viewMode', 'board')" aria-pressed="{{ $viewMode === 'board' ? 'true' : 'false' }}">Heftspiegel</button>
                            <button type="button" wire:click="$set('viewMode', 'list')" aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}">Liste</button>
                        </div>
                    </div>

                    @if ($this->canMutatePlan())
                        <div class="regenbogen-issue-planning__summary">
                            @foreach ([['Gesamtumfang', $summary['total_pages']], ['Platziert', $summary['placed_pages']], ['Noch nicht platziert', $summary['unplaced_reserved_pages']], ['Freie Kapazität', $summary['remaining_pages']], ['Druckfertige Seiten', $summary['print_ready_pages']]] as [$label, $value])
                                <div><span>{{ $label }}</span><strong>{{ rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',') }}</strong></div>
                            @endforeach
                            <div><span>Redaktionell fertig</span><strong>{{ $summary['editorially_ready_entries'] }} / {{ $entries->count() }}</strong></div>
                        </div>
                        @if ($summary['overbooked_pages'] > 0)
                            <p class="regenbogen-issue-planning__warning" role="alert">Die geplanten Inhalte überschreiten den Gesamtumfang um {{ number_format((float) $summary['overbooked_pages'], 2, ',', '') }} Seiten.</p>
                        @endif
                    @error('page_start') <p class="regenbogen-issue-planning__warning" role="alert">{{ $message }}</p> @enderror
                    @error('issue_id') <p class="regenbogen-issue-planning__warning" role="alert">{{ $message }}</p> @enderror
                    @endif
                </x-filament::section>

                @if ($entryFormOpen && $this->canMutatePlan())
                    <x-filament::section>
                        <x-slot name="heading">{{ $editingEntryId ? 'Heftplatz bearbeiten' : 'Beitrag einplanen' }}</x-slot>
                        @php($selectedArticle = $this->selectedArticle())
                        <form wire:submit="saveEntry" class="regenbogen-issue-entry-form">
                            @if ($this->isStructuralEntryForm())
                                <div class="regenbogen-issue-entry-form__wide regenbogen-issue-entry-form__info">
                                    @if (($entryForm['content_type'] ?? null) === 'cover')
                                        Die Titelseite wird als eigener Platzhalter geplant. CMS-Artikel werden hier nicht verknüpft.
                                    @else
                                        Die Schlussseite ist ein eigener Platzhalter am Ende des Hefts. CMS-Artikel werden hier nicht verknüpft.
                                    @endif
                                </div>
                            @else
                                <label>
                                    <span>CMS-Artikel (optional)</span>
                                    <select wire:model.live="entryForm.article_id" class="fi-input">
                                        <option value="">Platzhalter ohne Artikel</option>
                                        @foreach ($this->articleOptions() as $articleId => $articleTitle)
                                            <option value="{{ $articleId }}">{{ $articleTitle }}</option>
                                        @endforeach
                                    </select>
                                    <small>Es erscheinen nur freie Artikel und der aktuell verknüpfte Artikel.</small>
                                </label>
                            @endif
                            <label>
                                @if ($selectedArticle)
                                    <span>Titel aus dem CMS-Artikel</span>
                                    <input type="text" class="fi-input" value="{{ $selectedArticle->title }}" readonly>
                                @elseif ($this->isStructuralEntryForm())
                                    <span>Platzhalter</span>
                                    <input type="text" class="fi-input" value="{{ $entryForm['title'] }}" readonly>
                                @else
                                    <span>Arbeitstitel *</span>
                                    <input wire:model="entryForm.title" type="text" maxlength="255" class="fi-input" placeholder="z. B. Interview mit XY">
                                @endif
                            </label>
                            <label>
                                <span>Inhaltsart</span>
                                @if ($selectedArticle)
                                    <div class="fi-input regenbogen-issue-entry-form__readonly">Artikel{{ $selectedArticle->rubric_name ? ' · Rubrik: '.$selectedArticle->rubric_name : '' }}</div>
                                @elseif ($this->isStructuralEntryForm())
                                    <div class="fi-input regenbogen-issue-entry-form__readonly">{{ $this->contentTypeOptions()[$entryForm['content_type']] }}</div>
                                @else
                                    <select wire:model="entryForm.content_type" class="fi-input">
                                        @foreach ($this->entryContentTypeOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </label>
                            <label>
                                <span>Umfang in Seiten</span>
                                <input wire:model="entryForm.page_span" type="number" min="0.25" max="9999.75" step="0.25" class="fi-input" required>
                            </label>
                            <label>
                                <span>Startseite</span>
                                @if ($this->isStructuralEntryForm())
                                    <div class="fi-input regenbogen-issue-entry-form__readonly">Seite {{ $entryForm['page_start'] }}</div>
                                @else
                                    <select wire:model="entryForm.page_start" class="fi-input">
                                        <option value="">Noch nicht platziert</option>
                                        @for ($page = 2; $page <= (int) $issue->page_count; $page++)
                                            <option value="{{ $page }}">Seite {{ $page }}</option>
                                        @endfor
                                    </select>
                                @endif
                            </label>
                            <label>
                                <span>Startposition auf der Seite</span>
                                @if ($this->isStructuralEntryForm())
                                    <div class="fi-input regenbogen-issue-entry-form__readonly">Seitenanfang</div>
                                @else
                                    <input wire:model="entryForm.page_offset" type="number" min="0" max="0.75" step="0.25" class="fi-input" aria-describedby="issue-page-offset-help">
                                    <small id="issue-page-offset-help">0 = Seitenanfang, 0,25 = Viertel, 0,5 = Mitte, 0,75 = Dreiviertel. Für die Folgeseite bitte die Startseite ändern.</small>
                                @endif
                            </label>
                            <label>
                                <span>Platzierungsart</span>
                                @if ($this->isStructuralEntryForm())
                                    <div class="fi-input regenbogen-issue-entry-form__readonly">Einseitiger Platzhalter</div>
                                @else
                                    <select wire:model="entryForm.placement_type" class="fi-input">
                                        <option value="normal">Normaler Beitrag</option>
                                        <option value="spread">Echte Doppelseite (gegenüberliegende Seiten)</option>
                                    </select>
                                @endif
                            </label>
                            <label>
                                <span>Planungsstand</span>
                                <select wire:model="entryForm.planning_status" class="fi-input">
                                    <option value="idea">Idee</option><option value="planned">In Planung</option>
                                    <option value="research">Recherche</option><option value="writing">In Arbeit</option><option value="ready">Redaktionell fertig</option>
                                </select>
                            </label>
                            <label>
                                <span>Printproduktion</span>
                                <select wire:model="entryForm.print_status" class="fi-input">
                                    <option value="planned">Vorgesehen</option><option value="ready_for_layout">Bereit für den Satz</option>
                                    <option value="in_layout">Im Satz</option><option value="layout_ready">Gesetzte Fassung vorhanden</option>
                                    <option value="proofing">Schlusskorrektur</option><option value="approved">Druckfreigabe</option>
                                </select>
                            </label>
                            <label>
                                <span>Autor*innen</span>
                                @if ($selectedArticle)
                                    <div class="fi-input regenbogen-issue-entry-form__readonly">
                                        {{ $selectedArticle->authors->pluck('name')->join(', ') ?: 'Keine Autor*innen im Artikel hinterlegt' }}
                                    </div>
                                    <small>Autor*innen und Rubrik werden aus dem CMS-Artikel übernommen.</small>
                                @elseif ($this->isStructuralEntryForm())
                                    <div class="fi-input regenbogen-issue-entry-form__readonly">Keine Artikelzuordnung</div>
                                @else
                                    <select wire:model="entryForm.author_ids" multiple class="fi-input regenbogen-issue-entry-form__multiple">
                                        @foreach ($this->authorOptions() as $authorId => $authorName)
                                            <option value="{{ $authorId }}">{{ $authorName }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </label>
                            <label>
                                <span>Verantwortlich</span>
                                <select wire:model="entryForm.responsible_user_id" class="fi-input">
                                    <option value="">Keine Zuordnung</option>
                                    @foreach ($this->responsibleUserOptions() as $userId => $userName)
                                        <option value="{{ $userId }}">{{ $userName }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Abgabetermin</span>
                                <input wire:model="entryForm.due_at" type="date" class="fi-input">
                            </label>
                            <label class="regenbogen-issue-entry-form__wide">
                                <span>Interne Notizen</span>
                                <textarea wire:model="entryForm.internal_notes" rows="3" class="fi-input"></textarea>
                            </label>
                            @if ($errors->any())
                                <ul class="regenbogen-issue-planning__warning regenbogen-issue-entry-form__wide" role="alert">
                                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                </ul>
                            @endif
                            <div class="regenbogen-issue-entry-form__actions regenbogen-issue-entry-form__wide">
                                <button type="button" wire:click="closeEntryForm" class="fi-btn fi-btn-size-md fi-btn-color-gray">Abbrechen</button>
                                <button type="submit" class="fi-btn fi-btn-size-md fi-btn-color-primary">Heftplatz speichern</button>
                            </div>
                        </form>
                    </x-filament::section>
                @endif

                @if ($viewMode === 'list')
                    @include('filament.pages.partials.issue-plan-list', ['entries' => $entries, 'issue' => $issue, 'transferTargets' => $transferTargets])
                @else
                    @include('filament.pages.partials.issue-plan-spreads', ['entries' => $entries, 'issue' => $issue, 'spreads' => $this->spreads(), 'segments' => $segments])
                @endif
            @endif
        @endif
    </div>
</x-filament::page>
