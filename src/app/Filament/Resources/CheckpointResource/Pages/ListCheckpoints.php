<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Helpers\GeneralFrontendHelper;
use App\Filament\Resources\CheckpointResource;
use App\Models\DataStructures\CivitAIModelType;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCheckpoints extends ListRecords
{
    protected static string $resource = CheckpointResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Checkpoints';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            GeneralFrontendHelper::buildScanModelFolderAction(CivitAIModelType::CHECKPOINT),
            GeneralFrontendHelper::buildScanForModelUpdatesAction(CivitAIModelType::CHECKPOINT)
        ];
    }
}
