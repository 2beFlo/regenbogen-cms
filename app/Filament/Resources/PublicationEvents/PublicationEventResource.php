<?php

namespace App\Filament\Resources\PublicationEvents;

use App\Filament\Resources\PublicationEvents\Pages\ListPublicationEvents;
use App\Models\PublicationEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicationEventResource extends Resource
{
    protected static ?string $model = PublicationEvent::class;
    protected static ?string $navigationLabel = 'Protokoll';
    protected static ?string $modelLabel = 'Protokolleintrag';
    protected static ?string $pluralModelLabel = 'Veröffentlichungsprotokoll';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?int $navigationSort = 95;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Zeitpunkt')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('article.title')->label('Artikel')->searchable()->wrap(),
                TextColumn::make('summary')->label('Vorgang')->badge()->wrap(),
                TextColumn::make('user.name')->label('Benutzer*in')->placeholder('System'),
                TextColumn::make('ip_address')->label('IP-Adresse')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return ['index' => ListPublicationEvents::route('/')];
    }
}
