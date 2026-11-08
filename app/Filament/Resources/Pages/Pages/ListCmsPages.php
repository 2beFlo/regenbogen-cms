<?php
namespace App\Filament\Resources\Pages\Pages; use App\Filament\Resources\Pages\CmsPageResource; use Filament\Actions\CreateAction; use Filament\Resources\Pages\ListRecords; class ListCmsPages extends ListRecords { protected static string $resource=CmsPageResource::class; protected function getHeaderActions(): array { return [CreateAction::make()->label('Neue Seite')]; } }
