<?php

namespace App\Filament\Resources\ConfigResource\Pages;

use App\Filament\Resources\ConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListConfigs extends ListRecords
{
    protected static string $resource = ConfigResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Settings';
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
