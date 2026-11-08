<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('own-security')
                ->label('Mein Konto & 2FA')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->url(fn (): string => filament()->getProfileUrl() ?? '#')
                ->visible(fn (): bool => auth()->id() === $this->record->id),

            DeleteAction::make()
                ->visible(fn (): bool => UserResource::canDelete($this->record)),
        ];
    }
}
