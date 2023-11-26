<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Resources\CheckpointResource;
use App\Http\Helpers\Aria2Connector;
use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use App\Models\Tag;
use Closure;
use Filament\Actions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Get;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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
            Actions\Action::make('civit-import')
                ->form([Checkpoint::buildCivitAIDownloadWizard()])
                ->action(function ($data){
                    $metaData = CivitAIConnector::getModelMetaByID($data['modelID']);
                    $checkpoint = Checkpoint::with(['files', 'activedownloads'])->where('civitai_id', $data['modelID'])->first();
                    $canBeDownloaded = false;
                    if($checkpoint != null){
                        if(
                            $checkpoint->files->where('civitai_version', $data['version'])->first() == null &&
                            $checkpoint->activedownloads->where('version', $data['version'])->first() == null
                        ){
                            $canBeDownloaded = true;
                        }
                    } else {
                        Checkpoint::createNewCheckpointFromCivitAI($metaData, $data['sync_tags']);
                        $canBeDownloaded = true;
                    }

                    if($canBeDownloaded){
                        CivitDownload::downloadFileFromCivitAI(
                            CivitAIModelType::CHECKPOINT,
                            $data['modelID'],
                            $data['version'],
                            $data['sync_examples']
                        );
                    }
                })
                ->label('Import from CivitAI')
                ->button()
                ->modalSubmitAction(false)
                ->modalCancelAction(false),
            Actions\Action::make('scan_files')
                ->label('Scan checkpoint-files')
                ->button()
                ->action(fn() => Checkpoint::scanCheckpointFolderForNewFiles())
        ];
    }
}
