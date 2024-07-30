<?php

namespace App\Filament\Helpers;

use App\Http\Helpers\CivitAIConnector;
use App\Http\Helpers\GeneralHelper;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use App\Models\DataStructures\ModelBaseClassInterface;
use App\Models\Embedding;
use App\Models\EmbeddingFile;
use App\Models\Lora;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewModelHelper
{
    public static function buildCivitAILinkingAction($record) : Action
    {
        switch ($record->getCivitAIModelType()){
            case CivitAIModelType::CHECKPOINT:
                $recordType = 'Checkpoint';
                break;
            case CivitAIModelType::EMBEDDING:
                $recordType = 'LoRa';
                break;
            case CivitAIModelType::LORA:
                $recordType = 'Embedding';
                break;
        }
        return Action::make('link_to_civitai')
            ->label('Link this '.$recordType.' to CivitAI-Model')
            ->modalDescription('Beware: You do this on your own accountability! If you link this to a wrong model, that\'s on you! I can\'t really check that what you do here is correct. If you add an URL of an already existing model, it will be linked to that. (Sorry - can\'t really put a Embedding-Selector here...)')
            ->button()
            ->visible(!(bool)$record->civitai_id)
            ->form([self::buildCivitAILinkingWizard($record)])
            ->action(function ($data) use ($record){
                $redirect = GeneralFrontendHelper::runLinkingAction($data, $record);
                if($redirect > 0){
                    switch ($record->getCivitAIModelType()){
                        case CivitAIModelType::CHECKPOINT:
                            redirect('/checkpoints/'.$redirect);
                            break;
                        case CivitAIModelType::EMBEDDING:
                            redirect('/embeddings/'.$redirect);
                            break;
                        case CivitAIModelType::LORA:
                            redirect('/loras/'.$redirect);
                            break;
                    }
                }
            })
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }



    private static function buildCivitAILinkingWizard(ModelBaseClassInterface $oldModel): Wizard
    {
        return Wizard::make([
            CivitAIDownloadHelper::buildURLStep($oldModel->getCivitAIModelType(), true),
            self::buildModelFileVersionLinkingStep($oldModel),
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

    private static function buildModelFileVersionLinkingStep(ModelBaseClassInterface $oldModelBaseClass)
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

    public static function buildChangeNameAction($record) : \Filament\Infolists\Components\Actions
    {
        return \Filament\Infolists\Components\Actions::make([
            \Filament\Infolists\Components\Actions\Action::make('change_embeddingname')
                ->label('Change Embeddingname')
                ->button()
                ->form([
                    TextInput::make('model_name')
                        ->label(false)
                        ->default($record->model_name)
                ])
                ->action(function ($data) use($record){
                    $record->model_name = $data['model_name'];
                    $record->save();
                })
        ])->fullWidth();
    }

    public static function buildTagManagement($record) : array
    {
        $schema = [
            TextEntry::make('tags.tagname')
                ->label(false)
        ];
        $relationName = '';
        switch ($record->getCivitAIModelType()){
            case CivitAIModelType::CHECKPOINT:
                $relationName = 'checkpointTags';
                break;
            case CivitAIModelType::EMBEDDING:
                $relationName = 'embeddingTags';
                break;
            case CivitAIModelType::LORA:
                $relationName = 'loraTags';
                break;
        }
        $schema[] = \Filament\Infolists\Components\Actions::make([
            \Filament\Infolists\Components\Actions\Action::make('manage_tags')
                ->label('Manage Tags')
                ->button()
                ->modalHeading('Manage Tags')
                ->fillForm([$record])
                ->form([
                    Repeater::make('tag_relation')
                        ->label(false)
                        ->relationship($relationName)
                        ->schema([
                            Select::make('tag_id')
                                ->relationship('tag', 'tagname')
                        ])
                        ->addActionLabel('Add tag')
                        ->grid(3)
                ]),
        ])->columnSpan(3)->fullWidth();
        return $schema;
    }

    public static function buildUserNoteManagement($record) : array
    {
        return [
            TextEntry::make('user_notes')
                ->getStateUsing(fn() => $record->user_notes ? GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($record->user_notes) : 'You noted nothing so far...')
                ->label(false),
            \Filament\Infolists\Components\Actions::make([
                \Filament\Infolists\Components\Actions\Action::make('change_usernotes')
                    ->form([
                        RichEditor::make('notes')
                            ->label(false)
                            ->default($record->user_notes)
                    ])
                    ->action(function($data) use ($record){
                        $record->user_notes = $data['notes'];
                        $record->save();
                    }),
                \Filament\Infolists\Components\Actions\Action::make('clear_usernotes')
                    ->requiresConfirmation()
                    ->label('Clear usernotes')
                    ->button()
                    ->color('danger')
                    ->action(function () use ($record){
                        $record->user_notes = null;
                        $record->save();
                    })
            ])->fullWidth()
        ];
    }

    public static function buildMoveModelFileAction($modelFile) : \Filament\Infolists\Components\Actions\Action
    {
        return \Filament\Infolists\Components\Actions\Action::make('rename_modelfile_'.$modelFile->id)
            ->label('Change Filename/-path')
            ->button()
            ->form([
                Hidden::make('modelfile_id'),
                TextInput::make('file_path')
                    ->label(false)
                    ->hint('Please make sure you keep the correct the fileextention.')
            ])
            ->fillForm(function () use ($modelFile){
                $retval = [
                    'modelfile_id' => $modelFile->id,
                    'file_path' => $modelFile->filepath,
                ];
                return $retval;
            })
            ->action(function($data) use ($modelFile){
                $fileToChange = get_class($modelFile)::findOrFail($data['modelfile_id']);
                $disk = Storage::disk($fileToChange->diskname);
                $originalpath = $fileToChange->filepath;
                $modifiedPath = $data['file_path'];
                $disk->move($originalpath, $modifiedPath);
                $originalPreviewFilePath = GeneralHelper::getFilePathWithoutExtension($originalpath).'.preview.png';
                $modifiedPreviewFilePath = GeneralHelper::getFilePathWithoutExtension($modifiedPath).'.preview.png';
                if($disk->exists($originalPreviewFilePath)){
                    $disk->move($originalPreviewFilePath, $modifiedPreviewFilePath);
                }
                $fileToChange->filepath = $modifiedPath;
                $fileToChange->save();
                // let's remove the directorystructure if it's empty...
                $originalFolderStructure = explode('/', $originalpath);
                unset($originalFolderStructure[count($originalFolderStructure) - 1]); // remove original-filename
                if($originalFolderStructure > 0){
                    $pathToCheck = '';
                    for($x = 0; $x < count($originalFolderStructure); $x++){
                        $pathToCheck .= '/'.$originalFolderStructure[$x];
                        $pathIsEmpty = count($disk->allFiles($pathToCheck)) == 0;
                        if($pathIsEmpty){
                            $disk->deleteDirectory($pathToCheck);
                            break;
                        }
                    }
                }
            });
    }

    public static function buildDeleteModelFileAction($modelFile) : \Filament\Infolists\Components\Actions\Action
    {
        return \Filament\Infolists\Components\Actions\Action::make('delete_modelfile')
            ->label('Delete Modelfile')
            ->button()
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('If this is the last file in that model, the model will also be deleted. Are you sure you would like to do this?')
            ->form([Hidden::make('model_file_id')])
            ->fillForm(['model_file_id' => $modelFile->id])
            ->action(function ($data) use ($modelFile){
                $fileToDelete = get_class($modelFile)::with(['images', 'parentModel'])->findOrFail($data['model_file_id']);
                $fileToDelete->deleteModelFile();
                $parent = get_class($fileToDelete->parentModel)::with(['files'])->findOrFail($fileToDelete->base_id);
                if($parent->files->count() == 0){
                    $routeToRedirect = '';
                    switch ($parent->getCivitAIModelType()){
                        case CivitAIModelType::CHECKPOINT:
                            $routeToRedirect = 'filament.admin.resources.checkpoints.index';
                            break;
                        case CivitAIModelType::EMBEDDING:
                            $routeToRedirect = 'filament.admin.resources.embeddings.index';
                            break;
                        case CivitAIModelType::LORA:
                            $routeToRedirect = 'filament.admin.resources.loras.index';
                            break;
                    }
                    $parent->deleteModel();
                    redirect(route($routeToRedirect));
                }
            });
    }

    public static function buildModelFileMetaData($modelFile) : array
    {
        return [
            TextEntry::make('filepath')
                ->inlineLabel()
                ->label('FilePath:')
                ->getStateUsing($modelFile->filepath),
            TextEntry::make('version_name')
                ->label('Version:')
                ->inlineLabel()
                ->getStateUsing($modelFile->version_name)
                ->visible((bool)$modelFile->version_name),
            TextEntry::make('civitai_version')
                ->label('CivitAI-Version-ID/-Link')
                ->inlineLabel()
                ->getStateUsing($modelFile->civitai_version ? new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($modelFile->parentModel->civitai_id, $modelFile->civitai_version).'" target="_blank">'.$modelFile->civitai_version.'</a>') : '')
                ->visible((bool)$modelFile->civitai_version),
            TextEntry::make('baseModel')
                ->inlineLabel()
                ->label('Base Model:')
                ->getStateUsing($modelFile->baseModelType),
            TextEntry::make('trained_words')
                ->label('Trained words:')
                ->inlineLabel()
                ->getStateUsing(fn($record) => $record->trained_words ? implode(', ', json_decode($record->trained_words, true)) : '')
                ->visible(fn($record) => (bool)$record->trained_words),
            \Filament\Infolists\Components\Actions::make([
                ViewModelHelper::buildMoveModelFileAction($modelFile),
                ViewModelHelper::buildDeleteModelFileAction($modelFile),
            ])->fullWidth(),
        ];
    }
}
