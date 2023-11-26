<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use App\Models\Tag;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Tagmanager';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('unused')
                ->label('Remove unused tags')
                ->button()
                ->color('danger')
                ->action(function (){
                    foreach (Tag::get() as $tag){
                        if($tag->checkpoints()->count() == 0){
                            $tag->delete();
                        }
                    }
                })
                ->requiresConfirmation(),
            Actions\CreateAction::make(),
        ];
    }
}
