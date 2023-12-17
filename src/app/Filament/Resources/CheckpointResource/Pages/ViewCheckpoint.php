<?php

namespace App\Filament\Resources\CheckpointResource\Pages;

use App\Filament\Resources\CheckpointResource;
use App\Filament\Resources\CheckpointResource\Helpers\GeneralFrontendHelper;
use App\Http\Helpers\CivitAIConnector;
use App\Models\Checkpoint;
use App\Models\CheckpointFile;
use App\Models\CivitDownload;
use App\Models\DataStructures\CivitAIModelType;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewCheckpoint extends ViewRecord
{
    protected static string $resource = CheckpointResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'SC - '.$this->record->model_name;
    }

    public function getHeading(): string|Htmlable
    {
        return 'Checkpoint: '.$this->record->model_name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        if($this->record->files->count() == 0){
            return 'This checkpoint is still beeing downloaded! Please come back once it\'s finished.';
        } else {
            return null;
        }
    }

    public function getBreadcrumb(): string
    {
        return $this->record->model_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('link_to_civitai')
                ->label('Link this Checkpoint to CivitAI-Model')
                ->modalDescription('Beware: You do this on your own accountability! If you link this to a wrong model, that\'s on you! I can\'t really check that what you do here is correct. If you add an URL of an already existing model, it will be linked to that. (Sorry - can\'t really put a Checkpoint-Selector here...)')
                ->button()
                ->visible(!(bool)$this->record->civitai_id)
                ->form([GeneralFrontendHelper::buildCivitAILinkingWizard($this->record)])
                ->action(function ($data){
                    $redirect = GeneralFrontendHelper::runLinkingAction($data, $this->record);

                    if($redirect > 0){
                        $this->redirect('/checkpoints/'.$redirect);
                    }

                })
                ->modalSubmitAction(false)
                ->modalCancelAction(false),
            Actions\Action::make('download_additional_versions')
                ->label('Download additional versions')
                ->button()
                ->modalDescription('With this you can add other/older versions of this model to your collection.')
                ->form(function ($record){
                    return [
                        Select::make('versions')
                            ->label('Pick your versions')
                            ->multiple()
                            ->options($record->checkIfOtherVersionsExistOnCivitAi()),
                        Toggle::make('sync_images')
                            ->label('Sync example-images'),
                    ];
                })
                ->action(function ($data){
                    foreach ($data['versions'] as $version){
                        CivitDownload::downloadFileFromCivitAI(
                          CivitAIModelType::CHECKPOINT,
                            $this->record->civitai_id,
                            $version,
                            $data['sync_images']
                        );
                    }
                })
                ->visible(fn($record) => count($record->checkIfOtherVersionsExistOnCivitAi()) > 0)
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(function (){
                $retval = [
                    Section::make('Metadata')
                        ->columns(3)
                        ->schema([
                            GeneralFrontendHelper::buildImageEntryForDetailedViews($this->record),
                            Section::make()
                                ->schema([
                                    TextEntry::make('model_name')
                                        ->label('Checkpointname:')
                                        ->inlineLabel(),
                                    TextEntry::make('link')
                                        ->label('CivitAI-Link: ')
                                        ->inlineLabel()
                                        ->getStateUsing(fn($record) => new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'" target="_blank">'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($record->civitai_id).'</a>'))
                                        ->visible(fn($record) => $record->civitai_id != null),
                                    \Filament\Infolists\Components\Actions::make([
                                        Action::make('change_checkpointname')
                                            ->label('Change Checkpointname')
                                            ->button()
                                            ->form([
                                                TextInput::make('model_name')
                                                    ->label(false)
                                                    ->default($this->record->model_name)
                                            ])
                                            ->action(function ($data){
                                                $this->record->model_name = $data['model_name'];
                                                $this->record->save();
                                            }),
                                    ])
                                    ->fullWidth(),
                                    Section::make('Tags')
                                        ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                        ->schema(function (){
                                            $schema = [
                                                TextEntry::make('tags.tagname')
                                                    ->label(false)
                                            ];
                                            $schema[] = \Filament\Infolists\Components\Actions::make([
                                                Action::make('manage_tags')
                                                    ->label('Manage Tags')
                                                    ->button()
                                                    ->modalHeading('Manage Tags')
                                                    ->fillForm([$this->record])
                                                    ->form([
                                                        Repeater::make('checkpointTags')
                                                            ->label(false)
                                                            ->relationship()
                                                            ->schema([
                                                                Select::make('tag_id')
                                                                    ->relationship('tag', 'tagname')
                                                            ])
                                                            ->addActionLabel('Add tag')
                                                            ->grid(3)
                                                    ]),
                                            ])->columnSpan(3)->fullWidth();
                                            return $schema;
                                        }),
                                    Section::make('Your Notes')
                                        ->schema([
                                            TextEntry::make('user_notes')
                                                ->getStateUsing(fn() => $this->record->user_notes ? GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($this->record->user_notes) : 'You noted nothing so far...')
                                                ->label(false),
                                            \Filament\Infolists\Components\Actions::make([
                                                Action::make('change_usernotes')
                                                    ->form([
                                                        RichEditor::make('notes')
                                                            ->label(false)
                                                            ->default($this->record->user_notes)
                                                    ])
                                                    ->action(function($data){
                                                        $this->record->user_notes = $data['notes'];
                                                        $this->record->save();
                                                    }),
                                                Action::make('clear_usernotes')
                                                    ->requiresConfirmation()
                                                    ->label('Clear usernotes')
                                                    ->button()
                                                    ->color('danger')
                                                    ->action(function (){
                                                        $this->record->user_notes = null;
                                                        $this->record->save();
                                                    })
                                            ])->fullWidth()
                                        ]),
                                    Section::make('CivitAI-Description')
                                        ->schema([
                                            TextEntry::make('civitai_notes')
                                                ->getStateUsing(fn() => GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($this->record->civitai_notes))
                                                ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                                ->label(false)
                                        ])
                                        ->visible(fn() => $this->record->civitai_notes != null)
                                ])
                                ->columnSpan(2)
                        ]),

                ];
                foreach ($this->record->files->sortBy([
                    ['civitai_version', 'desc'],
                    ['id', 'desc']
                ]) as $checkpointFile){
                    $civitImages = [];
                    foreach ($checkpointFile->images->where('source', 'CivitAI') as $aiImage){
                        $civitImages[] = ImageEntry::make('aiImage_'.$aiImage->id)
                            ->label(false)
                            ->disk('ai_images')
                            ->getStateUsing($aiImage->filename)
                            ->columnSpan(function () use ($aiImage){
                                $dimensions = explode('x',$aiImage->initial_size);
                                if($dimensions[0] > $dimensions[1]){
                                    return 2;
                                } else {
                                    return 1;
                                }
                            })
                            ->action(
                                GeneralFrontendHelper::buildExampleImageViewAction($aiImage),
                            );
                    }
                    $retval[] = Section::make(basename($checkpointFile->filepath))
                        ->schema([
                            Section::make('CivitAI-Images')
                                ->schema($civitImages)
                                ->columns(10)
                                ->visible(fn() => count($civitImages) > 0),
                            Section::make('Metadata')
                                ->schema([
                                    Section::make()
                                        ->schema([
                                            TextEntry::make('filepath')
                                                ->inlineLabel()
                                                ->label('FilePath:')
                                                ->getStateUsing($checkpointFile->filepath),
                                            TextEntry::make('version_name')
                                                ->label('Version:')
                                                ->inlineLabel()
                                                ->getStateUsing($checkpointFile->version_name)
                                                ->visible((bool)$checkpointFile->version_name),
                                            TextEntry::make('civitai_version')
                                                ->label('CivitAI-Version-ID/-Link')
                                                ->inlineLabel()
                                                ->getStateUsing($checkpointFile->civitai_version ? new HtmlString('<a href="'.CivitAIConnector::buildCivitAILinkByModelAndVersionID($this->record->civitai_id, $checkpointFile->civitai_version).'" target="_blank">'.$checkpointFile->civitai_version.'</a>') : '')
                                                ->visible((bool)$checkpointFile->civitai_version),
                                            TextEntry::make('base_model')
                                                ->inlineLabel()
                                                ->label('Base Model:')
                                                ->getStateUsing($checkpointFile->baseModel),
                                            TextEntry::make('trained_words')
                                                ->label('Trained words:')
                                                ->inlineLabel()
                                                ->getStateUsing(fn($record) => $record->trained_words ? implode(', ', json_decode($record->trained_words, true)) : '')
                                                ->visible(fn($record) => (bool)$record->trained_words),
                                            \Filament\Infolists\Components\Actions::make([
                                                Action::make('rename_checkpointfile_'.$checkpointFile->id)
                                                    ->label('Change Filename')
                                                    ->button()
                                                    ->form([
                                                        Hidden::make('checkpointfile_id'),
                                                        TextInput::make('file_name')
                                                            ->label(false)
                                                            ->hint('Please make sure you keep the correct the fileextention!')
                                                    ])
                                                    ->fillForm(function () use ($checkpointFile){
                                                        $retval = [
                                                            'checkpointfile_id' => $checkpointFile->id,
                                                            'file_name' => basename($checkpointFile->filepath)
                                                        ];
                                                        return $retval;
                                                    })
                                                    ->action(function($data){
                                                        $checkpointFile = CheckpointFile::findOrFail($data['checkpointfile_id']);
                                                        $disk = Storage::disk('checkpoints');
                                                        $originalpath = $disk->path($checkpointFile->filepath);
                                                        $modifiedPath = $disk->path('').$data['file_name'];
                                                        rename($originalpath, $modifiedPath);
                                                        $checkpointFile->filepath = str_replace(Storage::disk('checkpoints')->path(''), '', $modifiedPath);
                                                        $checkpointFile->save();
                                                    }),
                                                Action::make('delete_checkpointfile')
                                                    ->label('Delete checkpointfile')
                                                    ->button()
                                                    ->color('danger')
                                                    ->requiresConfirmation()
                                                    ->modalDescription('If this is the last file in that checkpoint, the checkpoint will also be deleted. Are you sure you would like to do this?')
                                                    ->form([Hidden::make('checkpoint_file_id')])
                                                    ->fillForm(['checkpoint_file_id' => $checkpointFile->id])
                                                    ->action(function ($data){
                                                        $checkpointFile = CheckpointFile::with(['images'])->findOrFail($data['checkpoint_file_id']);
                                                        $checkpointID = $checkpointFile->base_id;
                                                        $checkpointFile->deleteModelFile();
                                                        $checkpoint = Checkpoint::with(['files'])->findOrFail($checkpointID);
                                                        if($checkpoint->files->count() == 0){
                                                            $checkpoint->deleteModel();
                                                            $this->redirect(route('filament.admin.resources.checkpoints.index'));
                                                        }

                                                    })
                                            ])->fullWidth(),
                                        ]),
                                    Section::make('CivitAI-Description')
                                        ->schema([
                                            TextEntry::make('civitai_notes')
                                                ->getStateUsing(fn() => $checkpointFile->civitai_description ? GeneralFrontendHelper::wrapHTMLStringToImplementBreaks($checkpointFile->civitai_description) : 'No additional informations given.')
                                                ->extraAttributes(['style' => 'max-height: 200px; overflow-y: scroll;'])
                                                ->label(false)
                                        ])->visible((bool)$this->record->civitai_id),
                                ])
                        ]);
                }
                return $retval;
            });
    }
}
