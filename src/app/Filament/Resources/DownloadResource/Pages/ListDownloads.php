<?php

namespace App\Filament\Resources\DownloadResource\Pages;

use App\Filament\Resources\DownloadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListDownloads extends ListRecords
{
    protected static string $resource = DownloadResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'CivitAI-Downloads';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
