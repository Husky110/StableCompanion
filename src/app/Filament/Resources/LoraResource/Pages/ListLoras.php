<?php

namespace App\Filament\Resources\LoraResource\Pages;

use App\Filament\Helpers\GeneralFrontendHelper;
use App\Filament\Resources\LoraResource;
use App\Models\DataStructures\CivitAIModelType;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListLoras extends ListRecords
{
    protected static string $resource = LoraResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'LoRAs';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            GeneralFrontendHelper::getImportFromCivitAIAction(CivitAIModelType::LORA),
            GeneralFrontendHelper::buildScanModelFolderAction(CivitAIModelType::LORA),
            GeneralFrontendHelper::buildScanForModelUpdatesAction(CivitAIModelType::LORA)
        ];
    }
}
