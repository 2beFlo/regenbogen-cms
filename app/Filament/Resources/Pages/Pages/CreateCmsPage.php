<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\CmsPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;

    protected static bool $canCreateAnother = false;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Seite speichern')
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
        return 'Seite gespeichert';
    }
}
