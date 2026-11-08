<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
}
