<?php

namespace App\Filament\Resources\Issues\Pages;

use App\Filament\Resources\Issues\IssueResource;
use App\Models\Issue;
use App\Services\IssuePlanningService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditIssue extends EditRecord
{
    protected static string $resource = IssueResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(IssuePlanningService::class)->updateIssue($record, $data);
    }
}
