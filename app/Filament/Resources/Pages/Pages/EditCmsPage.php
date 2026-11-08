<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\CmsPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Speichern')
                ->icon('heroicon-o-check')
                ->keyBindings(['mod+s'])
                ->extraAttributes(['class' => 'regenbogen-sticky-save'])
                ->formId('form'),

            DeleteAction::make()
                ->visible(fn (): bool => CmsPageResource::canDelete($this->record)),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Seite gespeichert';
    }
}
