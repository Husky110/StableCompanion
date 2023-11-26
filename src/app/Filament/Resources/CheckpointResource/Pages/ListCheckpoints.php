<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Resources\CheckpointResource;
use App\Filament\Resources\CheckpointResource\Helpers\CheckpointFilamentHelper;
use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
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
                ->action(fn() => Checkpoint::scanCheckpointFolderForNewFiles()),
            Actions\Action::make('update_civit_models')
                ->label('Scan for CivitAI-Modelupdates')
                ->button()
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->form(function (){
                    $modelsWithUpdates = [];
                    try{
                        $modelsToCheck = Checkpoint::whereNotNull('civitai_id')->orderBy('checkpoint_name')->get();
                        foreach ($modelsToCheck as $checkModel){
                            $latestCheckpointFile = CivitAIConnector::getModelMetaByID($checkModel->civitai_id)['modelVersions'][0];
                            $foundCheckpointFile = CheckpointFile::where([
                                ['checkpoint_id', '=', $checkModel->id],
                                ['civitai_version', '=', $latestCheckpointFile['id']]
                            ])->first();
                            if($foundCheckpointFile == null){
                                $modelsWithUpdates[] = $checkModel;
                            }
                        }
                    } catch (\Exception $exception){
                        return [
                          TextInput::make('none')
                            ->label(false)
                            ->default('CivitAI-API not reachable at the moment. Please try again later!')
                            ->disabled()
                        ];
                    }
                    if(count($modelsWithUpdates) == 0){
                        return [
                            TextInput::make('none')
                                ->label(false)
                                ->default('No models have updates yet.')
                                ->disabled()
                        ];
                    } else {
                        return [
                            Wizard::make([
                                Wizard\Step::make('Choose models to update')
                                    ->schema([
                                        CheckboxList::make('models_to_update')
                                            ->hint('This will only load the latest version of the checkpoint! In-between-versions have to be imported manually.')
                                            ->label(false)
                                            ->columns(3)
                                            ->bulkToggleable()
                                            ->options(function () use ($modelsWithUpdates){
                                                $options = [];
                                                foreach ($modelsWithUpdates as $model){
                                                    $options[$model->id] = $model->checkpoint_name;
                                                }
                                                return $options;
                                            })
                                            ->required()
                                    ]),
                                Wizard\Step::make('How to update')
                                    ->description('Here you can set your update-mode.')
                                    ->schema(function ($get){
                                        $retval = [];
                                        foreach ($get('models_to_update') as $item){
                                            $checkpoint = Checkpoint::find($item);
                                            $retval[] = Section::make($checkpoint->checkpoint_name)
                                                    ->columns()
                                                    ->schema([
                                                        Radio::make('updates.'.$checkpoint->id.'.mode')
                                                            ->label('Delete old versions')
                                                            ->boolean()
                                                            ->required()
                                                            ->columnSpan(1),
                                                        Radio::make('updates.'.$checkpoint->id.'.sync_images')
                                                            ->label('Load example-images')
                                                            ->boolean()
                                                            ->required()
                                                            ->columnSpan(1),

                                                    ]);
                                        }
                                        return $retval;
                                    })
                            ])->submitAction(new HtmlString(Blade::render(<<<BLADE
                        <x-filament::button
                            type="submit"
                            size="sm"
                        >
                            Submit
                        </x-filament::button>
        BLADE)))
                        ];
                    }
                })
                ->action(function($data){
                    if(isset($data['updates'])){
                        foreach ($data['updates'] as $checkPointID => $updateSettings){
                            if($updateSettings['mode']){
                                // delete all previous Versions!
                                $checkpoint = Checkpoint::with(['files'])->findOrFail($checkPointID);
                                foreach ($checkpoint->files as $checkpointFile){
                                    $checkpointFile->deleteCheckpointFile();
                                }
                            }
                            CivitDownload::downloadFileFromCivitAI(
                                CivitAIModelType::CHECKPOINT,
                                $checkpoint->civitai_id,
                                CivitAIConnector::getModelMetaByID($checkpoint->civitai_id)['modelVersions'][0]['id'],
                                $updateSettings['sync_images']
                            );

                        }
                    }
                })
        ];
    }
}
