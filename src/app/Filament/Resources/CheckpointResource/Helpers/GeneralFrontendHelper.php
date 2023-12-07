<?php

namespace App\Filament\Resources\CheckpointResource\Helpers;

use App\Http\Helpers\CivitAIConnector;
use App\Models\AIImage;
use App\Models\Checkpoint;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use App\Models\DataStructures\ModelBaseClassInterface;
use App\Models\Embedding;
use App\Models\Lora;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Infolists\Components\Actions\Action;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class GeneralFrontendHelper
{
    public static function buildExampleImageViewAction(AIImage $aiImage) : Action
    {
        return Action::make('view')
            ->modalHeading($aiImage->filename)
            ->form([
                Grid::make(3)
                    ->schema([
                        ViewField::make('image')
                            ->columnSpan(1)
                            ->view('filament.showImage')
                            ->viewData(['src' => '/ai_images/'.$aiImage->filename]),
                        \Filament\Forms\Components\Section::make('Metadata')
                            ->columnSpan(2)
                            ->columns()
                            ->schema([
                                Textarea::make($aiImage->id.'_positive')
                                    ->default($aiImage->positive)
                                    ->autosize()
                                    ->label('Positive Prompt')
                                    ->disabled(),
                                Textarea::make($aiImage->id.'_negative')
                                    ->default($aiImage->negative)
                                    ->autosize()
                                    ->label('Negative Prompt')
                                    ->disabled(),
                                TextInput::make($aiImage->id.'_sampler')
                                    ->default($aiImage->sampler)
                                    ->label('Sampler')
                                    ->disabled(),
                                TextInput::make($aiImage->id.'_cfg')
                                    ->default($aiImage->cfg)
                                    ->label('CFG-Scale')
                                    ->disabled(),
                                TextInput::make($aiImage->id.'_steps')
                                    ->default($aiImage->steps)
                                    ->label('Steps')
                                    ->disabled(),
                                TextInput::make($aiImage->id.'_seed')
                                    ->default($aiImage->seed)
                                    ->label('Seed')
                                    ->disabled(),
                                TextInput::make($aiImage->id.'_initial_size')
                                    ->default($aiImage->initial_size)
                                    ->label('Initial Size')
                                    ->disabled(),
                            ])
                    ])
            ])
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    public static function wrapHTMLStringToImplementBreaks(string $htmlString) : HtmlString
    {
        return new HtmlString('<div style="word-break: break-word">'.$htmlString.'</div>');
    }

    public static function buildCivitAIDownloadWizard(CivitAIModelType $modelType) : Wizard
    {
        return Wizard::make([
            CivitAIDownloadHelper::buildURLStep($modelType),
            CivitAIDownloadHelper::buildVersionSelectForDownloadWizardStep($modelType)
        ])->submitAction(GeneralFrontendHelper::getSumbitActionForForms());
    }

    public static function getImportFromCivitAIAction(CivitAIModelType $modelType) : \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('civit-import')
            ->form(function () use ($modelType){
                return[self::buildCivitAIDownloadWizard($modelType)];
            })
            ->action(function ($data) use ($modelType){
                $metaData = CivitAIConnector::getModelMetaByID($data['modelID']);
                /**
                 * @var ModelBaseClassInterface $modelBaseClass
                 */
                switch ($modelType){
                    case CivitAIModelType::CHECKPOINT:
                        $modelBaseClass = Checkpoint::with(['files', 'activedownloads'])->where('civitai_id', $data['modelID'])->first();
                        if($modelBaseClass == null){
                            Checkpoint::createNewModelFromCivitAI($metaData, $data['sync_tags']);
                        }
                        break;
                    case CivitAIModelType::LORA:
                        $modelBaseClass = Lora::with(['files', 'activedownloads'])->where('civitai_id', $data['modelID'])->first();
                        if($modelBaseClass == null){
                            Lora::createNewModelFromCivitAI($metaData, $data['sync_tags']);
                        }
                        break;
                    case CivitAIModelType::EMBEDDING:
                        $modelBaseClass = Embedding::with(['files', 'activedownloads'])->where('civitai_id', $data['modelID'])->first();
                        if($modelBaseClass == null){
                            Embedding::createNewModelFromCivitAI($metaData, $data['sync_tags']);
                        }
                        break;
                    default:
                        throw new \Exception('Unknown Modeltype!');
                }

                foreach ($data['download_versions'] as $versionToDownload){
                    if($modelBaseClass){
                        if(
                            $modelBaseClass->files->where('civitai_version', $versionToDownload)->first() == null &&
                            $modelBaseClass->activedownloads->where('version', $versionToDownload)->first() == null
                        ){
                            CivitDownload::downloadFileFromCivitAI(
                                $modelType,
                                $data['modelID'],
                                $versionToDownload,
                                $data['sync_examples']
                            );
                        }
                    } else {
                        CivitDownload::downloadFileFromCivitAI(
                            $modelType,
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
            ->modalCancelAction(false);
    }

    public static function getSumbitActionForForms() : HtmlString
    {
        return new HtmlString(Blade::render(<<<BLADE
                        <x-filament::button
                            type="submit"
                            size="sm"
                        >
                            Submit
                        </x-filament::button>
        BLADE));
    }

    private static function buildCheckpointFileVersionLinkingStep(ModelBaseClassInterface $oldModelBaseClass)
    {
        return Step::make('Versions')
            ->description('Link exisiting files')
            ->schema(function () use ($oldModelBaseClass){
                $retval = [
                    Hidden::make('modelID'),
                    Hidden::make('versions')->live(),
                ];
                foreach ($oldModelBaseClass->files as $modelFiles){
                    $retval[] =
                        Section::make(basename($modelFiles->filepath))
                            ->schema([
                                Select::make('files.'.$modelFiles->id.'.version')
                                    ->label('Select Version')
                                    ->options(function ($get){
                                        $knownVersions = json_decode($get('versions'), true);
                                        $knownVersions['custom'] = 'Old / Custom version';
                                        return $knownVersions;
                                    })
                                    ->required()
                                    ->hint('Sorting is newest to oldest.'),
                                Toggle::make('files.'.$modelFiles->id.'.sync_examples')
                                    ->label('Download example-images')
                                    ->default(true)
                                    ->hint('The CivitAI-API provides up to 10 images. We sync only images and only those that have complete informations.'),
                            ]);
                }
                return $retval;
            });
    }

    public static function buildCivitAILinkingWizard(ModelBaseClassInterface $oldModel): Wizard
    {
        return Wizard::make([
            CivitAIDownloadHelper::buildURLStep($oldModel->getCivitAIModelType()),
            self::buildCheckpointFileVersionLinkingStep($oldModel),
            Wizard\Step::make('Closure')
                ->description('Manage Duplicates')
                ->schema([
                    Toggle::make('sync_tags')
                        ->label('Sync Tags')
                        ->hint('Syncs all tags from CivitAI to this model.')
                        ->default(true),
                    Toggle::make('remove_duplicates')
                        ->label('Delete duplicate Files')
                        ->hint('Weither to keep duplicates or delete them - also applies to your previous selection (except custom versions), so doublecheck! If active, StableCompanion will keep the already existing file and delete this one.')
                ])
        ])->submitAction(GeneralFrontendHelper::getSumbitActionForForms());
    }

    public static function buildScanModelFolderAction(CivitAIModelType $modelType) : \Filament\Actions\Action
    {
        $action = \Filament\Actions\Action::make('scan_files')
            ->button();
        switch ($modelType){
            case CivitAIModelType::CHECKPOINT:
                $action->label('Scan checkpoint-files');
                $action->action(fn() => Checkpoint::checkModelFolderForNewFiles());
                break;
            case CivitAIModelType::LORA:
                $action->label('Scan lora-files');
                $action->action(fn() => Lora::checkModelFolderForNewFiles());
                break;
            case CivitAIModelType::EMBEDDING:
                $action->label('Scan embeddings-files');
                $action->action(fn() => Embedding::checkModelFolderForNewFiles());
                break;
            default:
                throw new \Exception('Unknown Modeltype');
        }
        return $action;

    }

    public static function buildScanForModelUpdatesAction(CivitAIModelType $modelType) : \Filament\Actions\Action
    {
        $action = \Filament\Actions\Action::make('update_civit_models')
            ->label('Scan for CivitAI-Modelupdates')
            ->button()
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->form(function () use ($modelType){
                $modelsWithUpdates = [];
                try{
                    $modelsToCheck = $modelType->value::whereNotNull('civitai_id')->orderBy('model_name')->get();
                    foreach ($modelsToCheck as $checkModel){
                        $latestModelFile = CivitAIConnector::getModelMetaByID($checkModel->civitai_id)['modelVersions'][0];
                        $foundModelFile = ($modelType->value::getModelFileClass())::where([
                            ['base_id', '=', $checkModel->id],
                            ['civitai_version', '=', $latestModelFile['id']]
                        ])->first();
                        if($foundModelFile == null){
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
                                            ->hint('This will only load the latest version of the model! In-between-versions have to be imported manually.')
                                            ->label(false)
                                            ->columns(3)
                                            ->bulkToggleable()
                                            ->options(function () use ($modelsWithUpdates){
                                                $options = [];
                                                foreach ($modelsWithUpdates as $model){
                                                    $options[$model->id] = $model->model_name;
                                                }
                                                return $options;
                                            })
                                            ->required()
                                    ]),
                                Wizard\Step::make('How to update')
                                    ->description('Here you can set your update-mode.')
                                    ->schema(function ($get) use ($modelType){
                                        $retval = [];
                                        foreach ($get('models_to_update') as $item){
                                            $model = $modelType->value::find($item);
                                            $retval[] = Section::make($model->model_name)
                                                ->columns()
                                                ->schema([
                                                    Radio::make('updates.'.$model->id.'.mode')
                                                        ->label('Delete old versions')
                                                        ->boolean()
                                                        ->required()
                                                        ->columnSpan(1)
                                                        ->default(false),
                                                    Radio::make('updates.'.$model->id.'.sync_images')
                                                        ->label('Load example-images')
                                                        ->boolean()
                                                        ->required()
                                                        ->columnSpan(1),
                                                ]);
                                        }
                                        return $retval;
                                    })
                            ])->submitAction(self::getSumbitActionForForms())
                        ];
                }
            })
            ->action(function($data) use ($modelType){
                if(isset($data['updates'])){
                    foreach ($data['updates'] as $checkPointID => $updateSettings){
                        $model = $modelType->value::with(['files'])->findOrFail($checkPointID);
                        if($updateSettings['mode']){
                            // delete all previous Versions!
                            foreach ($model->files as $modelFile){
                                $modelFile->deleteModelFile();
                            }
                        }
                        CivitDownload::downloadFileFromCivitAI(
                            $modelType,
                            $model->civitai_id,
                            CivitAIConnector::getModelMetaByID($model->civitai_id)['modelVersions'][0]['id'],
                            $updateSettings['sync_images']
                        );
                    }
                }
            });
        return $action;
    }

    public static function runLinkingAction(array $data, ModelBaseClassInterface $model) : int
    {
        $redirect = 0;
        $exisitingModel = $model::class::where('civitai_id', $data['modelID'])->first();
        if($exisitingModel){
            $redirect = $exisitingModel->id;
            if($data['remove_duplicates']){
                foreach ($exisitingModel->files as $existingFile){
                    foreach ($data['files'] as $linkingLoraFileID => $linkingData){
                        if($linkingData['version'] == 'custom'){
                            continue;
                        }
                        if($linkingData['version'] == $existingFile->version){
                            $existingFile->deleteModelFile();
                            unset($data['files'][$linkingLoraFileID]);
                            break;
                        }
                    }
                }
            }
            foreach ($model->files as $oldFile){
                $oldFile->base_id = $exisitingModel->id;
                $oldFile->civitai_version = $data['files'][$oldFile->id]['version'];
                $modelFileSpecificData = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($data['modelID'], $oldFile->civitai_version);
                $oldFile->civitai_description = $modelFileSpecificData['description'];
                switch ($model::class){
                    case Checkpoint::class:
                        $oldFile->baseModel = $modelFileSpecificData['baseModel'];
                        $oldFile->trained_words = isset($modelFileSpecificData['trainedWords']) ? json_encode($modelFileSpecificData['trainedWords'], JSON_UNESCAPED_UNICODE) : null;
                        break;
                    case Lora::class:
                        $oldFile->baseModelType = $modelFileSpecificData['baseModel'];
                        $oldFile->trained_words = isset($modelFileSpecificData['trainedWords']) ? json_encode($modelFileSpecificData['trainedWords'], JSON_UNESCAPED_UNICODE) : null;
                        break;
                }
                $oldFile->save();
            }
            $model->deleteModel();
            if($data['sync_tags']){
                $modelData = CivitAIConnector::getModelMetaByID($exisitingModel->civitai_id);
                $exisitingModel->syncCivitAITags($modelData);
            }

        } else {
            $modelData = CivitAIConnector::getModelMetaByID($data['modelID']);
            $model->model_name = $modelData['name'];
            $model->civitai_id = $modelData['id'];
            $model->setModelImage($modelData);
            $model->civitai_notes = $modelData['description'];
            $model->save();
            if($data['sync_tags']){
                $model->syncCivitAITags($modelData);
            }
            foreach ($model->files as $modelFile){
                $modelFile->civitai_version = $data['files'][$modelFile->id]['version'];
                $modelFileSpecificData = CivitAIConnector::getSpecificModelVersionByModelIDAndVersionID($data['modelID'], $modelFile->civitai_version);
                $modelFile->civitai_description = $modelFileSpecificData['description'];
                switch ($model::class){
                    case Checkpoint::class:
                        $modelFile->baseModel = $modelFileSpecificData['baseModel'];
                        $modelFile->trained_words = isset($modelFileSpecificData['trainedWords']) ? json_encode($modelFileSpecificData['trainedWords'], JSON_UNESCAPED_UNICODE) : null;
                        break;
                    case Lora::class:
                    case Embedding::class:
                        $modelFile->baseModelType = $modelFileSpecificData['baseModel'];
                        $modelFile->trained_words = isset($modelFileSpecificData['trainedWords']) ? json_encode($modelFileSpecificData['trainedWords'], JSON_UNESCAPED_UNICODE) : null;
                        break;
                }
                $modelFile->save();
            }
        }
        // okay, now we check if we have to load image-files from CivitAI...
        foreach ($data['files'] as $dataModelFileID => $dataFile){
            if($dataFile['sync_examples']){
                //Reload the ModelFile - just in case...
                $modelFileToChange = $model::class::getModelFileClass()::with(['parentModel'])->findOrFail($dataModelFileID);
                $modelFileToChange->loadImagesFromCivitAIForThisFile();
            }
        }
        return $redirect;
    }
}
