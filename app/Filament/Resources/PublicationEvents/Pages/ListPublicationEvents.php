<?php

namespace App\Filament\Resources\PublicationEvents\Pages;

use App\Filament\Resources\PublicationEvents\PublicationEventResource;
use Filament\Resources\Pages\ListRecords;

class ListPublicationEvents extends ListRecords
{
    protected static string $resource = PublicationEventResource::class;
}
