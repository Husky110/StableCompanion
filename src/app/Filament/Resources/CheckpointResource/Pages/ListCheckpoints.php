<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Resources\CheckpointResource;
use App\Filament\Resources\CheckpointResource\Helpers\CheckpointFilamentHelper;
use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use Filament\Actions;
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
            Actions\Action::make('civit-import')
                ->form([CheckpointFilamentHelper::buildCivitAIDownloadWizard()])
                ->action(function ($data){
                    $metaData = CivitAIConnector::getModelMetaByID($data['modelID']);
                    $checkpoint = Checkpoint::with(['files', 'activedownloads'])->where('civitai_id', $data['modelID'])->first();
                    if($checkpoint == null){
                        Checkpoint::createNewCheckpointFromCivitAI($metaData, $data['sync_tags']);
                    }

                    foreach ($data['download_versions'] as $versionToDownload){
                        if($checkpoint){
                            if(
                                $checkpoint->files->where('civitai_version', $versionToDownload)->first() == null &&
                                $checkpoint->activedownloads->where('version', $versionToDownload)->first() == null
                            ){
                                CivitDownload::downloadFileFromCivitAI(
                                    CivitAIModelType::CHECKPOINT,
                                    $data['modelID'],
                                    $versionToDownload,
                                    $data['sync_examples']
                                );
                            }
                        } else {
                            CivitDownload::downloadFileFromCivitAI(
                                CivitAIModelType::CHECKPOINT,
                                $data['modelID'],
                                $versionToDownload,
                                $data['sync_examples']
                            );
                        }
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
