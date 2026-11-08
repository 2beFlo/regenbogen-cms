<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\ArticleRevision;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = ArticleStatus::Draft->value;

        if (! auth()->user()?->isEditorial()) {
            $data['featured'] = false;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        ArticleRevision::capture($this->record->fresh(['authors']), auth()->id(), 'Artikel angelegt');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Entwurf speichern')
                ->icon('heroicon-o-check')
                ->keyBindings(['mod+s'])
                ->extraAttributes(['class' => 'regenbogen-sticky-save'])
                ->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Entwurf gespeichert';
    }
}
