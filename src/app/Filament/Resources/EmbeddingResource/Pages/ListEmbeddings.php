<?php

namespace App\Filament\Resources\EmbeddingResource\Pages;

use App\Filament\Helpers\GeneralFrontendHelper;
use App\Filament\Resources\EmbeddingResource;
use App\Models\DataStructures\CivitAIModelType;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListEmbeddings extends ListRecords
{
    protected static string $resource = EmbeddingResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Embeddings/Textual Inversions';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            GeneralFrontendHelper::getImportFromCivitAIAction(CivitAIModelType::EMBEDDING),
            GeneralFrontendHelper::buildScanModelFolderAction(CivitAIModelType::EMBEDDING),
            GeneralFrontendHelper::buildScanForModelUpdatesAction(CivitAIModelType::EMBEDDING)
        ];
    }
}
