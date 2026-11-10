<?php

namespace App\Filament\Resources\Pages;

use App\Filament\RichContent\IframeRichContentPlugin;
use App\Filament\Forms\Components\SeoFields;
use App\Filament\Resources\Pages\Pages;
use App\Models\CmsPage;
use BackedEnum;
use Filament\Actions\EditAction;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $navigationLabel = 'Seiten';
    protected static ?string $modelLabel = 'Seite';
    protected static ?string $pluralModelLabel = 'Seiten';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'xl' => 12,
            ])
                ->schema([
                    Group::make([
                        TextInput::make('title')
                            ->label('Seitentitel')
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['class' => 'regenbogen-editor-title']),

                        AdvancedRichEditor::make('content')
                            ->label('Inhalt')
                            ->toolbarButtons([
                                ['undo', 'redo'],
                                'divider',
                                ['headings', 'bold', 'italic', 'underline'],
                                'divider',
                                ['lists', 'blockquote', 'link'],
                                'divider',
                                ['image', 'table', 'embed'],
                                'divider',
                                'pin',
                                ['find', 'sourceCode', 'fullscreen', 'help'],
                            ])
                            ->plugins([IframeRichContentPlugin::make()])
                            ->sourceCode()
                            ->slashGroups([
                                'Text' => ['paragraph', 'headings', 'bulletList', 'orderedList', 'blockquote'],
                                'Medien' => ['image', 'embed', 'table'],
                            ])
                            ->fileAttachmentsDisk('uploads')
                            ->fileAttachmentsDirectory('editor')
                            ->fileAttachmentsVisibility('public')
                            ->mediaLibraryDirectory('editor')
                            ->fontSize(false)
                            ->fontPicker(false)
                            ->textColor(false)
                            ->textBackground(false)
                            ->customColors(false)
                            ->emoji(false)
                            ->characters(false)
                            ->lineHeight(false)
                            ->textDirection(false)
                            ->helperText('Visuell bearbeiten oder über „Quelltext“ direkt den HTML-Code öffnen. Mit „Vollbild“ lässt sich die Seite ohne Ablenkung bearbeiten.')
                            ->extraAttributes(['class' => 'regenbogen-page-editor'])
                            ->columnSpanFull(),

                        SeoFields::make(),
                    ])
                        ->extraAttributes(['class' => 'regenbogen-editor-main'])
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 9,
                        ]),

                    Group::make([
                        Section::make('Veröffentlichen')
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Entwurf',
                                        'published' => 'Veröffentlicht',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->native(false),
                            ]),

                        Section::make('Navigation')
                            ->description('Wo soll diese Seite auf der Website verlinkt werden?')
                            ->schema([
                                Toggle::make('show_in_navigation')
                                    ->label('In der Hauptnavigation anzeigen'),
                                Toggle::make('show_in_footer')
                                    ->label('In der Fußzeile anzeigen')
                                    ->default(true),
                            ]),

                        Section::make('Seiteneinstellungen')
                            ->collapsed()
                            ->schema([
                                TextInput::make('slug')
                                    ->label('URL')
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Optional; wird sonst automatisch aus dem Seitentitel erzeugt.'),
                                TextInput::make('sort_order')
                                    ->label('Reihenfolge')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Kleinere Zahlen erscheinen vor größeren.'),
                            ]),
                    ])
                        ->extraAttributes(['class' => 'regenbogen-editor-sidebar'])
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 3,
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Seite')->searchable(),
                TextColumn::make('slug')->label('URL'),
                TextColumn::make('status')->label('Status')->badge(),
                IconColumn::make('show_in_navigation')->label('Navigation')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isEditorial();
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isEditorial();
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->isEditorial();
    }

    public static function canDelete($record): bool
    {
        return (bool) auth()->user()?->isEditorial()
            && ! $record->isProtectedSystemPage();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsPages::route('/'),
            'create' => Pages\CreateCmsPage::route('/create'),
            'edit' => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }
}
