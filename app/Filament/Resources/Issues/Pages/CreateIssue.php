<?php

namespace App\Filament\Resources\Issues\Pages;

use App\Filament\Pages\IssuePlanning;
use App\Filament\Resources\Issues\IssueResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

class CreateIssue extends CreateRecord
{
    protected static string $resource = IssueResource::class;

    #[Url(as: 'from', history: false)]
    public ?string $from = null;

    protected function getRedirectUrl(): string
    {
        if ($this->from === 'planning' && $this->getRecord()) {
            return IssuePlanning::getUrl(['issue' => $this->getRecord()->getKey()]);
        }

        return parent::getRedirectUrl();
    }
}
