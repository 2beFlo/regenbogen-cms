<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationLabel = 'Benutzer*innen';
    protected static ?string $modelLabel = 'Benutzer*in';
    protected static ?string $pluralModelLabel = 'Benutzer*innen';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required(),
            TextInput::make('email')->label('E-Mail')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')
                ->label('Rolle')
                ->options(collect(UserRole::cases())->mapWithKeys(
                    fn (UserRole $role): array => [$role->value => $role->label()]
                )->all())
                ->helperText('Autor*innen schreiben Entwürfe. Redaktion darf veröffentlichen. Administration verwaltet zusätzlich Benutzer*innen und Website-Einstellungen.')
                ->required(),
            TextInput::make('password')
                ->label('Passwort')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('email')->label('E-Mail')->searchable(),
                TextColumn::make('role')
                    ->label('Rolle')
                    ->formatStateUsing(fn (UserRole | string $state): string => $state instanceof UserRole
                        ? $state->label()
                        : UserRole::from($state)->label()),
                IconColumn::make('mfa')
                    ->label('Anmeldeschutz')
                    ->boolean()
                    ->state(fn (User $record): bool => $record->hasStrongAuthenticationConfigured())
                    ->tooltip(fn (User $record): string => $record->requiresStrongAuthentication()
                        ? ($record->hasStrongAuthenticationConfigured() ? 'Pflicht erfüllt' : 'Für diese Rolle noch einzurichten')
                        : ($record->hasStrongAuthenticationConfigured() ? 'Freiwillig aktiviert' : 'Für Autor*innen optional')),
            ])
            ->recordActions([
                Action::make('security')
                    ->label('Sicherheit')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn (User $record): string => filament()->getProfileUrl() ?? '#')
                    ->visible(fn (User $record): bool => auth()->id() === $record->id),
                EditAction::make(),
            ]);
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canDelete($record): bool
    {
        return (bool) auth()->user()?->isAdmin() && auth()->id() !== $record->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
