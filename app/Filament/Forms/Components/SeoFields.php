<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Validation\Rule;

class SeoFields
{
    public static function make(): Section
    {
        return Section::make('Suchmaschinen & Teilen')
            ->collapsed()
            ->description('Leere Felder verwenden die globalen Vorgaben oder passende Inhalte dieser Seite.')
            ->schema([
                TextInput::make('seo_title')->label('SEO-Titel')->maxLength(255),
                Textarea::make('seo_description')->label('SEO-Beschreibung')->rows(3)->maxLength(320),
                Select::make('seo_robots')
                    ->label('Suchmaschinen-Zugriff')
                    ->options([
                        'inherit' => 'Globale Vorgabe verwenden',
                        'index,follow' => 'Indexieren und Links folgen',
                        'index,nofollow' => 'Indexieren, Links nicht folgen',
                        'noindex,follow' => 'Nicht indexieren, Links folgen',
                        'noindex,nofollow' => 'Nicht indexieren, Links nicht folgen',
                    ])
                    ->default('inherit')
                    ->formatStateUsing(fn ($state): string => $state ?: 'inherit')
                    ->dehydrateStateUsing(fn ($state): ?string => $state === 'inherit' ? null : $state)
                    ->rules([Rule::in(['inherit', 'index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'])])
                    ->required()
                    ->native(false)
                    ->helperText('Die Einstellung gilt für die Suchmaschinen-Meta-Angabe und die Aufnahme in die Sitemap.'),
                TextInput::make('seo_canonical_url')
                    ->label('Canonical-URL')
                    ->url()
                    ->rules(['regex:/^https:\/\/[^\s]+$/i'])
                    ->maxLength(2048)
                    ->helperText('Leer = die URL dieser Seite. Eine abweichende URL muss die bevorzugte, inhaltlich gleiche Seite sein.'),
                TextInput::make('og_title')->label('Titel für Social Media')->maxLength(255),
                Textarea::make('og_description')->label('Beschreibung für Social Media')->rows(3)->maxLength(320),
                FileUpload::make('og_image')
                    ->label('Social-Media-Bild')
                    ->disk('uploads')
                    ->directory('seo')
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(8192)
                    ->imageEditor()
                    ->preventFilePathTampering()
                    ->helperText('Leer = Artikelbild oder globales Standardbild.'),
            ])
            ->columns(2);
    }
}
