<?php
namespace App\Filament\Resources\Authors;
use App\Filament\Resources\Authors\Pages;
use App\Models\Author;
use App\Models\User;
use App\Enums\UserRole;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationLabel = 'Autor*innen';
    protected static ?string $modelLabel = 'Autor*in';
    protected static ?string $pluralModelLabel = 'Autor*innen';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 2;
    public static function form(Schema $schema): Schema { return $schema->components([
        TextInput::make('name')->label('Name')->required()->maxLength(255),
        TextInput::make('email')->label('E-Mail')->email(),
        Select::make('user_id')->label('Redakteur*innen-Konto')->placeholder('Kein Konto verknüpft')
            ->options(fn (?Author $record): array => User::query()
                ->where('role', UserRole::Redakteur->value)
                ->whereNotIn('id', Author::query()->whereNotNull('user_id')
                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->pluck('user_id'))
                ->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()->nullable()->unique(ignoreRecord: true)
            ->helperText('Nur explizit verknüpfte Profile können für die eigene Heftplanung zugeordnet werden.'),
        Textarea::make('bio')->label('Kurzvorstellung')->rows(5)
            ->helperText('Wird am Ende der Artikel als optionale Autorenbox angezeigt.')
            ->columnSpanFull(),
        TextInput::make('slug')->label('URL')->unique(ignoreRecord: true)->helperText('Optional; wird sonst automatisch erzeugt.'),
    ])->columns(2); }
    public static function table(Table $table): Table { return $table->columns([
        TextColumn::make('name')->label('Name')->searchable()->sortable(),
        TextColumn::make('articles_count')->counts('articles')->label('Artikel'),
        TextColumn::make('email')->label('E-Mail')->toggleable(),
    ])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]); }
    public static function canViewAny(): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canCreate(): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canEdit($record): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canDelete($record): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function getPages(): array { return ['index'=>Pages\ListAuthors::route('/'),'create'=>Pages\CreateAuthor::route('/create'),'edit'=>Pages\EditAuthor::route('/{record}/edit')]; }
}
