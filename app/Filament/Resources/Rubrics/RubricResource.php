<?php

namespace App\Filament\Resources\Rubrics;

use App\Filament\Resources\Rubrics\Pages;
use App\Models\Rubric;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RubricResource extends Resource
{
    protected static ?string $model = Rubric::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationLabel = 'Rubriken';
    protected static ?string $modelLabel = 'Rubrik';
    protected static ?string $pluralModelLabel = 'Rubriken';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Name der Rubrik')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->label('Kurzbeschreibung')
                ->helperText('Optional. Dient der Redaktion als Orientierung.')
                ->rows(4),
            TextInput::make('sort_order')
                ->label('Reihenfolge')
                ->numeric()
                ->default(100)
                ->helperText('Kleinere Zahlen erscheinen zuerst.'),
            Toggle::make('is_active')
                ->label('Für neue Artikel auswählbar')
                ->default(true),
            TextInput::make('slug')
                ->label('URL-Kürzel')
                ->helperText('Optional; wird sonst automatisch aus dem Namen erzeugt.')
                ->unique(ignoreRecord: true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Rubrik')->searchable()->sortable(),
                TextColumn::make('articles_count')->counts('articles')->label('Artikel')->sortable(),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
                TextColumn::make('sort_order')->label('Reihenfolge')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canCreate(): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canEdit($record): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canDelete($record): bool { return (bool) auth()->user()?->isEditorial() && $record->articles()->count() === 0; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRubrics::route('/'),
            'create' => Pages\CreateRubric::route('/create'),
            'edit' => Pages\EditRubric::route('/{record}/edit'),
        ];
    }
}
