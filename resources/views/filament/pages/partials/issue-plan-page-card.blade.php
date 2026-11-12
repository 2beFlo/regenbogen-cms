@php($pageSegments = $segments[$pageNumber] ?? [])

<div class="regenbogen-flatplan-page" aria-label="Seite {{ $pageNumber }}">
    <header class="regenbogen-flatplan-page__head">
        <span>Seite {{ $pageNumber }}</span>
        @if ($pageNumber === 1) <small>Titelseite</small> @endif
    </header>

    <div class="regenbogen-flatplan-page__slots"
        @if ($this->canMutatePlan())
            x-on:dragover.prevent
            x-on:drop.prevent="const id = Number($event.dataTransfer.getData('text/plain')); const box = $el.getBoundingClientRect(); const offset = Math.max(0, Math.min(.9999, ($event.clientY - box.top) / box.height)); if (id) $wire.moveEntryToPage(id, {{ $pageNumber }}, offset)"
        @endif>
        @forelse ($pageSegments as $segment)
            @php($entry = $entries->get($segment['entry_id']))
            @if ($entry)
                <article class="regenbogen-flatplan-entry {{ $entry->is_locked ? 'is-locked' : '' }}"
                    style="--entry-top: {{ $segment['offset'] * 100 }}%; --entry-height: {{ $segment['extent'] * 100 }}%"
                    @if ($this->canMutatePlan() && ! $entry->is_locked)
                        draggable="true"
                        x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $entry->id }}')"
                    @endif>
                    <div class="regenbogen-flatplan-entry__topline">
                        <span>{{ $this->contentTypeOptions()[$entry->content_type] ?? $entry->content_type }}</span>
                        @if ($entry->placement_type === 'spread') <b>Doppelseite</b> @elseif ($segment['offset'] > 0) <b>Teilfläche</b> @endif
                        @if ($entry->is_locked) <b aria-label="Gesperrt">Gesperrt</b> @endif
                    </div>
                    <strong>{{ $entry->display_title }}{{ $segment['offset'] > 0 ? ' · Fortsetzung' : '' }}</strong>
                    <small>{{ number_format($entry->page_span, 2, ',', '') }} Seiten · {{ $this->editorialStatusLabel($entry) }} · {{ $this->printStatusLabel($entry) }}</small>
                    @if ($entry->display_authors->isNotEmpty()) <small>{{ $entry->display_authors->pluck('name')->join(', ') }}</small> @elseif ($entry->responsibleUser) <small>{{ $entry->responsibleUser->name }}</small> @endif
                    @if ($this->canSeeInternalNotes() && filled($entry->internal_notes)) <small class="regenbogen-flatplan-entry__note">{{ $entry->internal_notes }}</small> @endif
                    @if ($this->canMutatePlan())
                        <div class="regenbogen-flatplan-entry__actions">
                            <button type="button" wire:click="openEditEntry({{ $entry->id }})" aria-label="Eintrag bearbeiten">Bearbeiten</button>
                            <button type="button" wire:click="toggleEntryLock({{ $entry->id }})">{{ $entry->is_locked ? 'Entsperren' : 'Sperren' }}</button>
                            @if ($this->articleEditUrl($entry)) <a href="{{ $this->articleEditUrl($entry) }}">Artikel öffnen</a> @endif
                            @if ($entry->page_start !== null) <button type="button" wire:click="unplaceEntry({{ $entry->id }})">Lösen</button> @endif
                        </div>
                    @elseif ($this->articleEditUrl($entry))
                        <a class="regenbogen-flatplan-entry__article" href="{{ $this->articleEditUrl($entry) }}">Eigenen Artikel öffnen</a>
                    @endif
                </article>
            @endif
        @empty
            <div class="regenbogen-flatplan-free">
                <span>Frei</span>
                @if ($this->canMutatePlan()) <button type="button" wire:click="openEntryForm({{ $pageNumber }})">Beitrag hier einplanen</button> @endif
            </div>
        @endforelse
    </div>
</div>
