<?php

namespace App\Filament\Resources\Articles;

use App\Filament\RichContent\IframeRichContentPlugin;
use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\Pages;
use App\Models\Article;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $navigationLabel = 'Artikel';
    protected static ?string $modelLabel = 'Artikel';
    protected static ?string $pluralModelLabel = 'Artikel';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 1;

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
                            ->label('Überschrift')
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['class' => 'regenbogen-editor-title']),

                        Textarea::make('teaser')
                            ->label('Vorspann')
                            ->helperText('Ein kurzer Einstieg, der neugierig macht und den Kern des Artikels setzt.')
                            ->rows(4)
                            ->required(),

                        AdvancedRichEditor::make('content')
                            ->label('Artikeltext')
                            ->required()
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
                            ->helperText('Wie in WordPress: normal schreiben und formatieren. Über „Quelltext“ lässt sich der HTML-Code direkt bearbeiten; „Vollbild“ schafft eine ruhige Schreibansicht.')
                            ->extraAttributes(['class' => 'regenbogen-writing-editor'])
                            ->columnSpanFull(),
                    ])
                        ->extraAttributes(['class' => 'regenbogen-editor-main'])
                        ->columnSpan([
                            'default' => 1,
                            'xl' => 9,
                        ]),

                    Group::make([
                        Section::make('Veröffentlichen')
                            ->description(fn (): string => auth()->user()?->isEditorial()
                                ? 'Status und Zeitpunkt des Artikels.'
                                : 'Du speicherst hier als Entwurf. Die Veröffentlichung übernimmt die Redaktion.')
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        ArticleStatus::Draft->value => 'Entwurf',
                                        ArticleStatus::Review->value => 'Zur Freigabe',
                                        ArticleStatus::Published->value => 'Veröffentlicht',
                                    ])
                                    ->default(ArticleStatus::Draft->value)
                                    ->required()
                                    ->native(false)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText(fn (): string => auth()->user()?->isEditorial()
                                        ? 'Der Veröffentlichungsstatus wird bewusst über die Schaltfläche oben geändert.'
                                        : 'Die Veröffentlichung übernimmt die Redaktion.'),

                                DateTimePicker::make('published_at')
                                    ->label('Veröffentlichen ab')
                                    ->seconds(false)
                                    ->helperText('Leer = sofort, sobald der Status „Veröffentlicht“ ist.')
                                    ->visible(fn (): bool => (bool) auth()->user()?->isEditorial()),

                                Toggle::make('featured')
                                    ->label('Großer Aufmacher auf der Startseite')
                                    ->visible(fn (): bool => (bool) auth()->user()?->isEditorial()),


                                Textarea::make('review_message')
                                    ->label('Hinweis an die Redaktion')
                                    ->rows(3)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (?Article $record): bool => filled($record?->review_message)),

                                Textarea::make('review_feedback')
                                    ->label('Rückmeldung der Redaktion')
                                    ->rows(4)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (?Article $record): bool => filled($record?->review_feedback)),
                            ]),

                        Section::make('Einordnung')
                            ->schema([
                                Select::make('rubric_id')
                                    ->label('Rubrik')
                                    ->relationship(
                                        name: 'rubric',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('sort_order')->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->createOptionForm([
                                        TextInput::make('name')->label('Neue Rubrik')->required()->maxLength(255),
                                        Textarea::make('description')->label('Kurzbeschreibung')->rows(3),
                                    ])
                                    ->createOptionAction(fn (Action $action): Action => $action
                                        ->label('Neue Rubrik anlegen')
                                        ->visible(fn (): bool => (bool) auth()->user()?->isEditorial()))
                                    ->createOptionUsing(function (array $data): int {
                                        abort_unless((bool) auth()->user()?->isEditorial(), 403);
                                        return \App\Models\Rubric::query()->create([
                                            'name' => $data['name'],
                                            'description' => $data['description'] ?? null,
                                            'sort_order' => 100,
                                            'is_active' => true,
                                        ])->getKey();
                                    }),

                                Select::make('authors')
                                    ->label('Autor*innen')
                                    ->relationship('authors', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Mehrere Namen sind ausdrücklich möglich.'),

                                Select::make('issue_id')
                                    ->label('Ausgabe')
                                    ->relationship('issue', 'title')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Nur online / noch keiner Ausgabe zugeordnet'),
                            ]),

                        Section::make('Artikelbild')
                            ->schema([
                                FileUpload::make('hero_image')
                                    ->label('Artikelbild / Aufmacherbild')
                                    ->helperText('Das große Bild für den Artikel und einen möglichen Aufmacher. Über das Stift-Symbol kannst du den Ausschnitt bearbeiten.')
                                    ->disk('uploads')
                                    ->directory('articles')
                                    ->image()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->imageEditor()
                                    ->imageEditorAspectRatioOptions([null, '16:9', '4:3', '1:1'])
                                    ->imageEditorViewportWidth('1600')
                                    ->imageEditorViewportHeight('900')
                                    ->preventFilePathTampering(),

                                FileUpload::make('card_image')
                                    ->label('Vorschaubild für die Artikelliste')
                                    ->helperText('Optional. Dieses Bild erscheint neben der Überschrift auf der Startseite. Es wird auf 4:3 zugeschnitten. Wenn es leer bleibt, verwendet die Website das Artikelbild.')
                                    ->disk('uploads')
                                    ->directory('articles/cards')
                                    ->image()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->imageEditor()
                                    ->imageAspectRatio('4:3')
                                    ->automaticallyOpenImageEditorForAspectRatio()
                                    ->imageEditorAspectRatioOptions(['4:3'])
                                    ->imageEditorViewportWidth('1200')
                                    ->imageEditorViewportHeight('900')
                                    ->preventFilePathTampering(),

                                TextInput::make('image_notice')
                                    ->label('Bildhinweis')
                                    ->placeholder('z. B. KI-generiertes Symbolbild')
                                    ->maxLength(255),

                                TextInput::make('image_alt')
                                    ->label('Alternativtext für Artikelbilder')
                                    ->helperText('Beschreibe knapp, was auf dem Artikelbild zu sehen ist. Wenn leer, wird die Artikelüberschrift verwendet.')
                                    ->maxLength(255),
                            ]),

                        Section::make('Suchmaschinen')
                            ->collapsed()
                            ->description('Optional. Ohne eigene Angaben verwendet die Website Überschrift und Vorspann.')
                            ->schema([
                                TextInput::make('seo_title')
                                    ->label('SEO-Titel')
                                    ->maxLength(255),
                                Textarea::make('seo_description')
                                    ->label('SEO-Beschreibung')
                                    ->rows(3)
                                    ->maxLength(320),
                            ]),

                        Section::make('Weitere Einstellungen')
                            ->collapsed()
                            ->schema([
                                TextInput::make('card_title')
                                    ->label('Kurztitel für Karten')
                                    ->helperText('Optional. Wenn leer, wird die normale Überschrift verwendet.'),

                                TextInput::make('slug')
                                    ->label('URL')
                                    ->helperText('Wird beim ersten Speichern automatisch aus der Überschrift erzeugt.')
                                    ->unique(ignoreRecord: true),
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
                TextColumn::make('title')->label('Artikel')->searchable()->sortable()->wrap(),
                TextColumn::make('rubric.name')->label('Rubrik')->placeholder('Ohne Rubrik')->toggleable(),
                TextColumn::make('authors.name')->label('Autor*innen')->listWithLineBreaks()->limitList(3),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(
                        fn (ArticleStatus | string $state): string => $state instanceof ArticleStatus
                            ? $state->label()
                            : ArticleStatus::from($state)->label()
                    )
                    ->color(fn (ArticleStatus | string $state): string => match ($state instanceof ArticleStatus ? $state : ArticleStatus::from($state)) {
                        ArticleStatus::Draft => 'gray',
                        ArticleStatus::Review => 'warning',
                        ArticleStatus::Published => 'success',
                    }),
                TextColumn::make('updated_at')->label('Zuletzt geändert')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn (): bool => (bool) auth()->user()?->isEditorial()),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['authors', 'issue', 'rubric']);
        $user = auth()->user();

        if ($user && ! $user->isEditorial()) {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isEditorial()) {
            return true;
        }

        return $record->created_by === $user->id && $record->status === ArticleStatus::Draft;
    }

    public static function canDelete($record): bool
    {
        return (bool) auth()->user()?->isEditorial();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
