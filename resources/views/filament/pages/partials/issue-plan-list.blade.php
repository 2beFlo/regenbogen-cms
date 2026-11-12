<x-filament::section>
    <x-slot name="heading">Alle Heftplätze</x-slot>
    @if ($entries->isEmpty())
        <p class="regenbogen-flatplan-empty">Für diese Ausgabe sind noch keine Beiträge eingeplant.</p>
    @else
        <div class="regenbogen-issue-plan-list">
            @foreach ($entries as $entry)
                <article class="regenbogen-issue-plan-row">
                    <div class="regenbogen-issue-plan-row__main">
                        <div class="regenbogen-issue-plan-row__title">
                            <strong>{{ $entry->display_title }}</strong>
                            @if ($this->articleEditUrl($entry)) <a href="{{ $this->articleEditUrl($entry) }}">Artikel öffnen</a> @endif
                        </div>
                        <p>
                            {{ $this->contentTypeOptions()[$entry->content_type] ?? $entry->content_type }}
                            · {{ $entry->page_start ? 'ab Seite '.$entry->page_start : 'Noch nicht platziert' }}
                            · {{ number_format($entry->page_span, 2, ',', '') }} Seiten
                            @if ($entry->placement_type === 'spread') · Doppelseite @endif
                        </p>
                        <p>{{ $this->editorialStatusLabel($entry) }} · Print: {{ $this->printStatusLabel($entry) }}</p>
                        @if ($entry->display_authors->isNotEmpty()) <p>Autor*innen: {{ $entry->display_authors->pluck('name')->join(', ') }}</p> @endif
                        @if ($entry->responsibleUser) <p>Verantwortlich: {{ $entry->responsibleUser->name }}</p> @endif
                        @if ($entry->due_at) <p>Abgabe: {{ $entry->due_at->format('d.m.Y') }}</p> @endif
                        @if ($this->canSeeInternalNotes() && filled($entry->internal_notes)) <p class="regenbogen-flatplan-note">{{ $entry->internal_notes }}</p> @endif
                    </div>

                    @if ($this->canMutatePlan())
                        <div class="regenbogen-issue-plan-row__actions">
                            @if (! in_array($entry->content_type, ['cover', 'closing_page'], true))
                                <div class="regenbogen-issue-plan-row__manual-place">
                                    <label>
                                        <span class="sr-only">Zielseite für {{ $entry->display_title }}</span>
                                        <select wire:model="manualPlacement.{{ $entry->id }}.page_start" class="fi-input">
                                            <option value="">Zielseite wählen</option>
                                            @for ($page = 2; $page <= (int) $issue->page_count; $page++)
                                                <option value="{{ $page }}">Seite {{ $page }}</option>
                                            @endfor
                                        </select>
                                    </label>
                                    <label>
                                        <span class="sr-only">Startposition auf der Seite: 0 bedeutet oben, 0,5 bedeutet Mitte</span>
                                        <input wire:model="manualPlacement.{{ $entry->id }}.page_offset" type="number" min="0" max="0.9999" step="0.0001" class="fi-input" value="{{ $entry->page_offset }}" aria-label="Startposition auf der Seite: 0 bedeutet oben, 0,5 bedeutet Mitte">
                                    </label>
                                    <button type="button" wire:click="manualMoveEntry({{ $entry->id }})" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Position setzen</button>
                                </div>
                                <div class="regenbogen-issue-plan-row__buttons">
                                    <button type="button" wire:click="openEditEntry({{ $entry->id }})" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Bearbeiten</button>
                                    <button type="button" wire:click="toggleEntryLock({{ $entry->id }})" class="fi-btn fi-btn-size-sm fi-btn-color-gray">{{ $entry->is_locked ? 'Entsperren' : 'Sperren' }}</button>
                                    @if ($entry->page_start !== null) <button type="button" wire:click="unplaceEntry({{ $entry->id }})" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Aus Heftspiegel lösen</button> @endif
                                </div>
                            @endif
                            @if ($transferTargets->isNotEmpty() && ! in_array($entry->content_type, ['cover', 'closing_page'], true))
                                <div class="regenbogen-issue-plan-row__manual-place">
                                    <label>
                                        <span class="sr-only">Zielausgabe für {{ $entry->display_title }}</span>
                                        <select wire:model="transferTarget.{{ $entry->id }}" class="fi-input">
                                            <option value="">In andere Ausgabe verschieben</option>
                                            @foreach ($transferTargets as $targetIssue)
                                                <option value="{{ $targetIssue->id }}">{{ $targetIssue->issue_code ?: $targetIssue->title }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="button" wire:click="transferEntryToIssue({{ $entry->id }})" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Verschieben</button>
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</x-filament::section>
