<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\ArticleRevision;
use App\Support\ArticleQualityCheck;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->isEditorial()) {
            $data['status'] = ArticleStatus::Draft->value;
            $data['featured'] = false;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        ArticleRevision::capture($this->record->fresh(['authors']), auth()->id(), 'Gespeichert');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Vorschau')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('articles.preview', $this->record))
                ->openUrlInNewTab(),

            $this->getSaveFormAction()
                ->label('Speichern')
                ->icon('heroicon-o-check')
                ->keyBindings(['mod+s'])
                ->extraAttributes(['class' => 'regenbogen-sticky-save'])
                ->formId('form'),

            Action::make('quality')
                ->label('Qualitätscheck')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->modalHeading('Qualitätscheck')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Schließen')
                ->modalContent(fn (): HtmlString => $this->qualityCheckHtml()),

            Action::make('versions')
                ->label('Versionen')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status !== ArticleStatus::Published
                    && $this->record->revisions()->exists())
                ->modalHeading('Frühere Version wiederherstellen')
                ->modalDescription('Die ausgewählte Fassung wird als neuer Entwurf übernommen. Nichts wird automatisch veröffentlicht.')
                ->schema([
                    Select::make('revision_id')
                        ->label('Version')
                        ->options(fn (): array => $this->record->revisions()
                            ->with('user')
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn (ArticleRevision $revision): array => [
                                $revision->id => 'Version '.$revision->version_number.' · '
                                    .$revision->created_at->format('d.m.Y H:i').' · '
                                    .($revision->user?->name ?? 'System').' · '.$revision->reason,
                            ])
                            ->all())
                        ->required()
                        ->native(false),
                ])
                ->requiresConfirmation()
                ->modalSubmitActionLabel('Version wiederherstellen')
                ->action(function (array $data): void {
                    $revision = $this->record->revisions()->findOrFail($data['revision_id']);
                    $revision->restoreToArticle();
                    ArticleRevision::capture($this->record->fresh(['authors']), auth()->id(), 'Version '.$revision->version_number.' wiederhergestellt');

                    Notification::make()
                        ->success()
                        ->title('Version als Entwurf wiederhergestellt')
                        ->send();

                    $this->redirect(ArticleResource::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('submit_review')
                ->label('Zur Freigabe')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn (): bool => ! auth()->user()?->isEditorial()
                    && $this->record->status === ArticleStatus::Draft)
                ->modalHeading('Artikel zur Freigabe einreichen')
                ->modalDescription('Die Redaktion bekommt den Artikel auf ihr Dashboard. Bis zur Rückgabe oder Freigabe ist die Fassung gesperrt.')
                ->schema([
                    Textarea::make('message')
                        ->label('Hinweis an die Redaktion')
                        ->placeholder('Optional: Was soll besonders geprüft werden?')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->modalSubmitActionLabel('Zur Freigabe einreichen')
                ->action(function (array $data): void {
                    $this->save();
                    $this->record->refresh();

                    if ($this->stopForQualityBlockers()) {
                        return;
                    }

                    $this->record->update([
                        'status' => ArticleStatus::Review->value,
                        'review_requested_at' => now(),
                        'review_requested_by' => auth()->id(),
                        'review_message' => $data['message'] ?? null,
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                        'review_feedback' => null,
                    ]);
                    ArticleRevision::capture($this->record->fresh(['authors']), auth()->id(), 'Zur Freigabe eingereicht');

                    Notification::make()->success()->title('Artikel ist bei der Redaktion zur Freigabe')->send();
                    $this->redirect(ArticleResource::getUrl());
                }),

            Action::make('return_for_changes')
                ->label('Zur Überarbeitung')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->isEditorial()
                    && $this->record->status === ArticleStatus::Review)
                ->schema([
                    Textarea::make('feedback')
                        ->label('Rückmeldung an die Autor*in')
                        ->placeholder('Was soll noch geändert oder geprüft werden?')
                        ->required()
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->modalSubmitActionLabel('Zur Überarbeitung zurückgeben')
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => ArticleStatus::Draft->value,
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                        'review_feedback' => $data['feedback'],
                    ]);
                    ArticleRevision::capture($this->record->fresh(['authors']), auth()->id(), 'Zur Überarbeitung zurückgegeben');
                    Notification::make()->success()->title('Artikel wurde zurückgegeben')->send();
                    $this->redirect(ArticleResource::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('publish')
                ->label(fn (): string => $this->record->published_at?->isFuture() ? 'Veröffentlichung planen' : 'Veröffentlichen')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => (bool) auth()->user()?->isEditorial()
                    && $this->record->status !== ArticleStatus::Published)
                ->requiresConfirmation()
                ->modalHeading('Artikel veröffentlichen?')
                ->modalDescription(function (): string {
                    $date = $this->data['published_at'] ?? null;
                    if ($date && now()->lt(\Illuminate\Support\Carbon::parse($date))) {
                        return 'Der Artikel wird gespeichert und ab dem eingestellten Zeitpunkt automatisch öffentlich sichtbar.';
                    }
                    return 'Der Artikel wird gespeichert und anschließend unmittelbar öffentlich sichtbar.';
                })
                ->modalSubmitActionLabel('Ja, veröffentlichen')
                ->action(function (): void {
                    $this->save();
                    $this->record->refresh();

                    if ($this->stopForQualityBlockers()) {
                        return;
                    }

                    $updates = [
                        'status' => ArticleStatus::Published->value,
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                        'review_feedback' => null,
                    ];
                    if (! $this->record->published_at) {
                        $updates['published_at'] = now();
                    }

                    $this->record->update($updates);
                    ArticleRevision::capture($this->record->fresh(['authors']), auth()->id(), 'Veröffentlicht');

                    Notification::make()
                        ->success()
                        ->title($this->record->published_at?->isFuture() ? 'Veröffentlichung geplant' : 'Artikel veröffentlicht')
                        ->send();

                    $this->redirect(ArticleResource::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('unpublish')
                ->label('Veröffentlichung zurücknehmen')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->isEditorial()
                    && $this->record->status === ArticleStatus::Published)
                ->requiresConfirmation()
                ->modalHeading('Veröffentlichung zurücknehmen?')
                ->modalDescription('Der Artikel bleibt im CMS erhalten, ist auf der Website aber nicht mehr öffentlich sichtbar.')
                ->modalSubmitActionLabel('Zurücknehmen')
                ->action(function (): void {
                    $this->record->update(['status' => ArticleStatus::Draft->value]);
                    ArticleRevision::capture($this->record->fresh(['authors']), auth()->id(), 'Veröffentlichung zurückgenommen');

                    Notification::make()
                        ->success()
                        ->title('Veröffentlichung zurückgenommen')
                        ->send();

                    $this->redirect(ArticleResource::getUrl('edit', ['record' => $this->record]));
                }),

            DeleteAction::make()
                ->visible(fn (): bool => (bool) auth()->user()?->isEditorial()),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Artikel gespeichert';
    }

    private function stopForQualityBlockers(): bool
    {
        $result = ArticleQualityCheck::run($this->record->fresh(['authors', 'rubric']));
        if (! $result['blockers']) {
            return false;
        }

        Notification::make()
            ->danger()
            ->title('Noch nicht bereit zur Freigabe')
            ->body(implode(' ', $result['blockers']))
            ->persistent()
            ->send();

        return true;
    }

    private function qualityCheckHtml(): HtmlString
    {
        $result = ArticleQualityCheck::run($this->record->fresh(['authors', 'rubric']));
        $section = static function (string $heading, array $items, string $icon): string {
            if (! $items) {
                return '';
            }
            $lines = collect($items)->map(fn (string $item): string => '<li style="margin:.35rem 0">'.e($item).'</li>')->implode('');
            return '<div style="margin-bottom:1rem"><strong>'.$icon.' '.e($heading).'</strong><ul style="margin:.4rem 0 0 1.25rem">'.$lines.'</ul></div>';
        };

        $html = $section('Muss vor Freigabe erledigt werden', $result['blockers'], '⛔');
        $html .= $section('Bitte prüfen', $result['warnings'], '⚠️');
        $html .= $section('In Ordnung', $result['passed'], '✓');

        return new HtmlString('<div style="line-height:1.5">'.$html.'</div>');
    }
}
