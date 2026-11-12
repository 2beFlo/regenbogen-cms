<div class="regenbogen-flatplan-wrap">
    @if (! $issue->page_count)
        <x-filament::section>
            <p class="text-sm text-gray-500">Lege die Gesamtseitenzahl in den Ausgabeneinstellungen fest, um den Heftspiegel aufzubauen.</p>
        </x-filament::section>
    @else
        <div class="regenbogen-flatplan" aria-label="Heftspiegel für {{ $issue->page_count }} Seiten">
            @foreach ($spreads as $spread)
                <section class="regenbogen-flatplan-spread" aria-label="{{ $spread['left'] ? 'Doppelseite '.$spread['left'].'–'.($spread['right'] ?: '') : 'Titelseite' }}">
                    @if ($spread['left'] === null)
                        <div class="regenbogen-flatplan-gutter" aria-hidden="true"></div>
                    @else
                        @include('filament.pages.partials.issue-plan-page-card', ['pageNumber' => $spread['left'], 'entries' => $entries, 'segments' => $segments])
                    @endif

                    @if ($spread['right'] === null)
                        <div class="regenbogen-flatplan-gutter" aria-hidden="true"></div>
                    @else
                        @include('filament.pages.partials.issue-plan-page-card', ['pageNumber' => $spread['right'], 'entries' => $entries, 'segments' => $segments])
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    @php($unplaced = $entries->filter(fn ($entry) => $entry->page_start === null))
    <x-filament::section>
        <x-slot name="heading">Noch nicht platziert</x-slot>
        @if ($unplaced->isEmpty())
            <p class="regenbogen-flatplan-empty">Keine reservierten Beiträge ohne Position.</p>
        @else
            <div class="regenbogen-unplaced-list">
                @foreach ($unplaced as $entry)
                    <article class="regenbogen-unplaced-card" @if ($this->canMutatePlan() && ! $entry->is_locked) draggable="true" x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $entry->id }}')" @endif>
                        <div>
                            <strong>{{ $entry->display_title }}</strong>
                            <span>{{ $this->contentTypeOptions()[$entry->content_type] ?? $entry->content_type }} · {{ number_format($entry->page_span, 2, ',', '') }} Seiten · {{ $this->editorialStatusLabel($entry) }}</span>
                            @if ($entry->display_authors->isNotEmpty()) <small>{{ $entry->display_authors->pluck('name')->join(', ') }}</small> @endif
                            @if ($this->canSeeInternalNotes() && filled($entry->internal_notes)) <small class="regenbogen-flatplan-note">{{ $entry->internal_notes }}</small> @endif
                        </div>
                        @if ($this->canMutatePlan())
                            <button type="button" wire:click="openEditEntry({{ $entry->id }})" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Eintrag bearbeiten</button>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</div>
