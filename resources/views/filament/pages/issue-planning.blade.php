<x-filament::page>
    @php($issues = $this->issues())
    @php($issue = $this->selectedIssue())
    @php($summary = $this->summary())

    <div class="space-y-6">
        @if ($issues->isEmpty())
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">Es gibt noch keine Ausgaben, die du ansehen kannst.</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <label class="block w-full sm:max-w-md">
                        <span class="mb-1 block text-sm font-medium">Ausgabe</span>
                        <select wire:model.live="selectedIssueId" class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5">
                            @foreach ($issues as $availableIssue)
                                <option value="{{ $availableIssue->id }}">{{ $availableIssue->issue_code ?: $availableIssue->title }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($issue && $this->canMutatePlan())
                        <a href="{{ \App\Filament\Resources\Issues\IssueResource::getUrl('edit', ['record' => $issue]) }}" class="fi-btn fi-btn-size-md fi-btn-color-gray">
                            Ausgabe bearbeiten
                        </a>
                    @endif
                </div>
            </x-filament::section>

            @if ($issue)
                <x-filament::section>
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold">{{ $issue->work_title ?: $issue->title }}</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $issue->page_count ?: 'Noch keine Seitenzahl' }} Seiten
                                @if ($issue->planned_publication_date) · geplant für {{ $issue->planned_publication_date->format('d.m.Y') }} @endif
                            </p>
                        </div>
                        <div class="flex gap-2" role="tablist" aria-label="Ansicht">
                            <button type="button" wire:click="$set('viewMode', 'board')" aria-pressed="{{ $viewMode === 'board' ? 'true' : 'false' }}" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Heftspiegel</button>
                            <button type="button" wire:click="$set('viewMode', 'list')" aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}" class="fi-btn fi-btn-size-sm fi-btn-color-gray">Liste</button>
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
                        @foreach ([['Gesamt', $summary['total_pages']], ['Platziert', $summary['placed_pages']], ['Noch ohne Platz', $summary['unplaced_reserved_pages']], ['Frei', $summary['remaining_pages']], ['Druckfertige Seiten', $summary['print_ready_pages']]] as [$label, $value])
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                <p class="text-xs text-gray-500">{{ $label }}</p>
                                <p class="mt-1 text-lg font-semibold">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-sm text-gray-500">{{ $summary['editorially_ready_entries'] }} Beiträge redaktionell fertig</p>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament::page>
