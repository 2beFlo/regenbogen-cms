<?php
namespace App\Filament\Resources\Issues;
use App\Filament\Forms\Components\SeoFields; use App\Filament\Resources\Issues\Pages; use App\Models\Issue; use BackedEnum; use Filament\Actions\BulkActionGroup; use Filament\Actions\DeleteBulkAction; use Filament\Actions\EditAction; use Filament\Forms\Components\DatePicker; use Filament\Forms\Components\FileUpload; use Filament\Forms\Components\Textarea; use Filament\Forms\Components\TextInput; use Filament\Forms\Components\Toggle; use Filament\Resources\Resource; use Filament\Schemas\Components\Section; use Filament\Schemas\Schema; use Filament\Tables\Columns\IconColumn; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table;
class IssueResource extends Resource
{
    protected static ?string $model=Issue::class; protected static ?string $recordTitleAttribute='title'; protected static ?string $navigationLabel='Ausgaben'; protected static ?string $modelLabel='Ausgabe'; protected static ?string $pluralModelLabel='Ausgaben'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-book-open'; protected static ?int $navigationSort=4;
    public static function form(Schema $schema): Schema { return $schema->components([
        Section::make('Ausgabe')->components([
            TextInput::make('title')->label('Bezeichnung')->required()->maxLength(255),
            TextInput::make('issue_code')->label('Ausgabennummer')->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('work_title')->label('Arbeitstitel / Themenschwerpunkt')->maxLength(255),
            TextInput::make('subtitle')->label('Untertitel'),
            DatePicker::make('planned_publication_date')->label('Geplantes Erscheinungsdatum'),
            DatePicker::make('editorial_deadline')->label('Redaktionsschluss'),
            DatePicker::make('print_date')->label('Drucktermin'),
            DatePicker::make('published_at')->label('Tatsächliches Erscheinungsdatum'),
            Toggle::make('is_current')->label('Als aktuelle Ausgabe anzeigen')->helperText('Wenn aktiviert, wird die bisherige aktuelle Ausgabe automatisch ins Archiv verschoben.'),
            \Filament\Forms\Components\Select::make('production_status')->label('Produktionsstatus')->options([
                'planning' => 'In Planung', 'editorial' => 'Redaktion', 'layout' => 'Im Satz',
                'proofing' => 'Schlusskorrektur', 'approved' => 'Druckfreigabe', 'printed' => 'Gedruckt', 'archived' => 'Abgeschlossen',
            ])->default('planning')->required()->native(false),
            Textarea::make('planning_notes')->label('Interne Notizen')->rows(4)->columnSpanFull(),
        ])->columns(2),
        Section::make('Dateien')->components([
            FileUpload::make('cover_image')->label('Cover')->disk('uploads')->directory('issues/covers')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->imageEditor()->preventFilePathTampering(), FileUpload::make('pdf_file')->label('PDF-Ausgabe')->disk('uploads')->directory('issues/pdf')->acceptedFileTypes(['application/pdf'])->maxSize(122880)->helperText('PDF-Dateien bis 120 MB')->preventFilePathTampering(),
            TextInput::make('page_count')->label('Gesamtseitenzahl')->numeric()->rules(['integer', 'min:1', 'max:65535'])->required()->helperText('Frei wählbar; auch ungerade Seitenzahlen sind möglich. Belegte Seiten verhindern ein unbemerktes Verkleinern.'), TextInput::make('format')->label('Format')->default('A5'),
        ])->columns(2),
        SeoFields::make(),
        Section::make('Weitere Einstellungen')->collapsed()->components([TextInput::make('slug')->label('URL')->unique(ignoreRecord:true)->helperText('Optional; wird sonst automatisch erzeugt.')]),
    ]); }
    public static function table(Table $table): Table { return $table->columns([
        TextColumn::make('title')->label('Ausgabe')->searchable()->sortable(), IconColumn::make('is_current')->label('Aktuell')->boolean(), TextColumn::make('published_at')->label('Erschienen')->date('d.m.Y')->sortable(), TextColumn::make('articles_count')->counts('articles')->label('Artikel'),
    ])->defaultSort('published_at','desc')->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]); }
    public static function canViewAny(): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canCreate(): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canEdit($record): bool { return (bool) auth()->user()?->isEditorial(); }
    public static function canDelete($record): bool { return (bool) auth()->user()?->isEditorial() && $record->entries()->doesntExist(); }
    public static function getPages(): array { return ['index'=>Pages\ListIssues::route('/'),'create'=>Pages\CreateIssue::route('/create'),'edit'=>Pages\EditIssue::route('/{record}/edit')]; }
}
