<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

/** @property-read Schema $form */
class SiteSettings extends Page
{
    protected string $view = 'filament.pages.site-settings';
    protected static ?string $navigationLabel = 'Website';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?int $navigationSort = 8;

    public ?array $data = [];

    public function getTitle(): string
    {
        return 'Website verwalten';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Kopfbereich')->components([
                        TextInput::make('masthead_kicker')->label('Zeile über REGENBOGEN')->required(),
                        TextInput::make('strapline')->label('Unterzeile')->required(),
                    ])->columns(2),
                    Section::make('SEO – globale Vorgaben')
                        ->description('Diese Werte gelten für die ganze Website, sofern eine Seite keine eigene Einstellung hat.')
                        ->components([
                            TextInput::make('site_title')->label('Name der Website')->required()->maxLength(255),
                            TextInput::make('seo_title_suffix')->label('Zusatz am Ende von Seitentiteln')->maxLength(100),
                            TextInput::make('seo_site_url')->label('Kanonische Website-Adresse')->url()->rules(['regex:/^https:\/\/[^\s]+$/i'])->required()->maxLength(255)
                                ->helperText('Öffentliche HTTPS-Adresse ohne abschließenden Schrägstrich, z. B. https://regenbogen-duderstadt.de'),
                            Select::make('seo_default_robots')->label('Standard für Suchmaschinen')
                                ->options(self::robotsOptions())
                                ->rules([Rule::in(array_keys(self::robotsOptions()))])
                                ->required()->native(false)
                                ->helperText('noindex verhindert die Aufnahme in Suchmaschinen. Die globale Vorgabe gilt für alle Inhalte ohne eigene Robots-Einstellung.'),
                            Textarea::make('seo_default_description')->label('Allgemeine SEO-Beschreibung')->rows(3)->maxLength(320)->columnSpanFull(),
                            FileUpload::make('seo_default_og_image')->label('Standardbild für Social Media')
                                ->disk('uploads')->directory('seo')->visibility('public')->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(8192)
                                ->imageEditor()->preventFilePathTampering()
                                ->helperText('Wird verwendet, wenn für eine Seite kein eigenes Social-Media-Bild hinterlegt ist.'),
                            TextInput::make('seo_publisher_name')->label('Name im strukturierten Herausgeber-Datensatz')->required()->maxLength(255),
                            TextInput::make('seo_publisher_url')->label('Herausgeber-URL')->url()->rules(['regex:/^https:\/\/[^\s]+$/i'])->required()->maxLength(255),
                            FileUpload::make('seo_publisher_logo')->label('Logo im strukturierten Herausgeber-Datensatz')
                                ->disk('uploads')->directory('seo')->visibility('public')->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(8192)
                                ->imageEditor()->preventFilePathTampering(),
                        ])->columns(2),
                    self::pageSeoSection('SEO – Startseite', 'seo_home', 'SEO-Titel der Startseite', 'SEO-Beschreibung der Startseite'),
                    self::pageSeoSection('SEO – Ausgabenarchiv', 'seo_issues', 'SEO-Titel des Ausgabenarchivs', 'SEO-Beschreibung des Ausgabenarchivs'),
                    Section::make('Über uns auf der Startseite')->components([
                        TextInput::make('about_title')->label('Überschrift')->required()->columnSpanFull(),
                        Textarea::make('about_text')->label('Text')->rows(5)->columnSpanFull(),
                        TextInput::make('about_link_label')->label('Linktext'),
                        TextInput::make('about_link_url')->label('Linkziel'),
                    ])->columns(2),
                    Section::make('Fußzeile')->components([
                        TextInput::make('footer_claim')->label('Claim')->required()->columnSpanFull(),
                        Textarea::make('publisher')->label('Herausgeberin')->rows(5),
                        Textarea::make('editorial')->label('Redaktion')->rows(5),
                        TextInput::make('contact_label')->label('Zusätzlicher Kontakttext'),
                        TextInput::make('contact_url')->label('Zusätzlicher Kontaktlink')->url(),
                        TextInput::make('frequency')->label('Erscheinungsweise')->columnSpanFull(),
                    ])->columns(2),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('Änderungen speichern')->submit('save')->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->record(SiteSetting::current())
            ->statePath('data');
    }

    public function save(): void
    {
        SiteSetting::current()->update($this->form->getState());
        Notification::make()->success()->title('Website gespeichert')->send();
    }

    private static function robotsOptions(): array
    {
        return [
            'index,follow' => 'Indexieren und Links folgen',
            'index,nofollow' => 'Indexieren, Links nicht folgen',
            'noindex,follow' => 'Nicht indexieren, Links folgen',
            'noindex,nofollow' => 'Nicht indexieren, Links nicht folgen',
        ];
    }

    private static function pageSeoSection(string $label, string $prefix, string $titleLabel, string $descriptionLabel): Section
    {
        return Section::make($label)
            ->description('Eigene Werte überschreiben die globalen Vorgaben. Die Robots-Auswahl „Globale Vorgabe“ übernimmt die Einstellung für die gesamte Website.')
            ->components([
                TextInput::make($prefix.'_title')->label($titleLabel)->maxLength(255),
                Textarea::make($prefix.'_description')->label($descriptionLabel)->rows(3)->maxLength(320)->columnSpanFull(),
                Select::make($prefix.'_robots')->label('Suchmaschinen-Zugriff')
                    ->options(['inherit' => 'Globale Vorgabe verwenden'] + self::robotsOptions())
                    ->default('inherit')
                    ->formatStateUsing(fn ($state): string => $state ?: 'inherit')
                    ->dehydrateStateUsing(fn ($state): ?string => $state === 'inherit' ? null : $state)
                    ->rules([Rule::in(['inherit', ...array_keys(self::robotsOptions())])])
                    ->required()->native(false),
                TextInput::make($prefix.'_canonical')->label('Canonical-URL')->url()->rules(['regex:/^https:\/\/[^\s]+$/i'])->maxLength(2048),
                TextInput::make($prefix.'_og_title')->label('Social-Media-Titel')->maxLength(255),
                Textarea::make($prefix.'_og_description')->label('Social-Media-Beschreibung')->rows(3)->maxLength(320),
                FileUpload::make($prefix.'_og_image')->label('Social-Media-Bild')
                    ->disk('uploads')->directory('seo')->visibility('public')->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(8192)
                    ->imageEditor()->preventFilePathTampering(),
            ])->columns(2);
    }
}
